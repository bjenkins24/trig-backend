<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Jobs\CardDedupe;
use App\Jobs\SaveCardData;
use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\CardType;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Integrations\Google\GoogleContent;
use App\Modules\Card\Integrations\Google\GoogleIntegration;
use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Modules\Permission\PermissionRepository;
use App\Utils\ExtractDataHelper;
use App\Utils\FileHelper;
use Google_Service_Drive as GoogleServiceDrive;
use Google_Service_Drive_Resource_Files as GoogleServiceDriveFiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\Modules\Card\Integrations\Google\Fakes\FileFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;
use Tests\Utils\ExtractDataHelperTest;

class SyncCardsTest extends TestCase
{
    use CreateOauthConnection;

    private function getMockThumbnail(): Collection
    {
        return collect([
            'thumbnail' => 'coolthumbnail',
            'extension' => 'jpg',
            'width'     => 200,
            'height'    => 500,
        ]);
    }

    private function getSetup(?User $user = null): array
    {
        if (! $user) {
            $user = User::find(1);
            $this->createOauthConnection($user);
        }
        $card = factory(Card::class)->create([
            'user_id' => $user->id,
        ]);
        $data = collect([
            'data' => collect([
                'user_id'            => $user->id,
                'delete'             => false,
                'card_type'          => 'application/vnd.google-apps.document',
                'url'                => 'https://docs.google.com/document/d/1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k/edit?usp=drivesdk',
                'foreign_id'         => '1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k',
                'title'              => 'Interview Template v0.1',
                'description'        => 'My cool description',
                'actual_created_at'  => '2018-12-22 12:30:00',
                'actual_modified_at' => '2018-12-22 12:30:00',
                'thumbnail_uri'      => 'https://docs.google.com/a/trytrig.com/feeds/vt?gd=true&id=1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k&v=14&s=AMedNnoAAAAAXpUOTCtmc4zTbEZ6g0EPywj-ypToA8-U&sz=s220',
            ]),
            'permissions' => collect([]),
        ]);

        return [$data, $user, $card];
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    private function getSyncCards(string $service = 'google'): SyncCardsIntegration
    {
        return app(OauthIntegrationService::class)->makeSyncCards($service);
    }

    private function getBase(array $data): SyncCardsIntegration
    {
        $this->mock(GoogleIntegration::class, static function ($mock) use ($data) {
            $mock->shouldReceive('getAllCardData')->andReturn($data);
            $mock->shouldReceive('getIntegrationKey')->andReturn('google');
        });
        $this->partialMock(FileHelper::class, function ($mock) {
            $mock->shouldReceive('fileGetContents');
            $mock->shouldReceive('getImageSizeFromString')->andReturn([
                0      => $this->getMockThumbnail()->get('width'),
                1      => $this->getMockThumbnail()->get('height'),
                'mime' => 'image/jpg',
            ]);
        });
        $syncCards = app(SyncCardsIntegration::class);
        $syncCards->setIntegration(app(GoogleIntegration::class), app(GoogleContent::class));

        return $syncCards;
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws CardIntegrationCreationValidate
     */
    public function testNoSyncWhenUpToDate(): void
    {
        $this->refreshDb();
        [$data] = $this->getSetup();
        $cardData = $data->get('data');
        $cardData->put('title', 'My cool title');
        $cardData->put('actual_modified_at', '1980-01-01 10:35:00');
        $cardIntegration = CardIntegration::find(1);
        $cardIntegration->foreign_id = $cardData->get('foreign_id');
        $cardIntegration->save();

        $this->getSyncCards()->upsertCard($data);

        $this->assertDatabaseMissing('cards', [
            'title' => $data->get('name'),
        ]);
    }

    /**
     * @throws CardIntegrationCreationValidate
     */
    public function testSyncCardsEmpty(): void
    {
        $syncCards = $this->getBase([]);
        $result = $syncCards->syncCards(User::find(1), time());
        self::assertFalse($result);
    }

    /**
     * @throws CardIntegrationCreationValidate
     * @group n
     */
    public function testSyncCards(): void
    {
        $this->refreshDb();
        $user = User::find(1);
        $this->createOauthConnection($user);
        $data = [
            [
                'data' => [
                    'user_id'            => 1,
                    'delete'             => false,
                    'card_type'          => 'random_card_type',
                    'url'                => 'mycoolurl',
                    'foreign_id'         => 'mycoolid',
                    'title'              => 'my cool title',
                    'description'        => 'My cool description',
                    'actual_created_at'  => '2020-12-22 12:30:00',
                    'actual_modified_at' => '2020-11-22 12:30:00',
                    'thumbnail_uri'      => 'https://asdasd.com',
                ],
                'permissions' => [
                    'users'      => [],
                    'link_share' => [],
                ],
            ],
        ];
        $syncCards = $this->getBase($data);

        $card = $data[0]['data'];
        $syncCards->syncCards($user, time());

        $cardType = CardType::where('name', '=', $card['card_type'])->first();
        $newCard = Card::where('title', '=', $card['title'])->first();

        $this->assertDatabaseHas('card_types', [
            'name' => $card['card_type'],
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'card_id'    => $newCard->id,
            'foreign_id' => $card['foreign_id'],
        ]);

        $this->assertDatabaseHas('cards', [
            'id'                 => $newCard->id,
            'user_id'            => $card['user_id'],
            'card_type_id'       => $cardType->id,
            'title'              => $card['title'],
            'description'        => $card['description'],
            'url'                => $card['url'],
            'actual_created_at'  => $card['actual_created_at'],
            'actual_modified_at' => $card['actual_modified_at'],
            'image'              => Config::get('app.url').Storage::url(SyncCardsIntegration::IMAGE_FOLDER.'/'.$newCard->id).'.jpg',
            'image_width'        => $this->getMockThumbnail()->get('width'),
            'image_height'       => $this->getMockThumbnail()->get('height'),
        ]);
    }

    public function testSyncCardsExistingCard()
    {
        $this->refreshDb();
        $user = $this->syncDomains();
        $file = new FileFake();
        $file->name = 'My cool title';
        $file->id = 'super fake id';
        $file->modifiedTime = '2020-06-24 12:00:00';
        $nextPageToken = 'next_page_token';
        Queue::fake();
        CardIntegration::create([
            'card_id'              => 1,
            'oauth_integration_id' => 1,
            'foreign_id'           => $file->id,
        ]);
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($file, $nextPageToken) {
            $mock->shouldNotReceive('createIntegration');
            $mock->shouldReceive('saveThumbnail')->once();
            $mock->shouldReceive('savePermissions')->once();
            $mock->shouldReceive('getNewNextPageToken')->andReturn($nextPageToken)->once();
            $mock->shouldReceive('listFilesFromService')->andReturn(collect([$file]))->once();
        });

        $result = app(GoogleIntegration::class)->syncCards($user->id);
        Queue::assertPushed(SaveCardData::class, 1);
        $this->refreshDb();
    }

    /**
     * @param $file
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     *
     * @return bool
     */
    private function syncCardsFail($file)
    {
        $user = User::find(1);
        $this->createOauthConnection($user);
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($file) {
            $mock->shouldReceive('getFiles')->andReturn(collect([$file]))->once();
        });

        return app(GoogleIntegration::class)->syncCards($user->id);
    }

    /**
     * If there are no files from google we should do nothing.
     *
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     *
     * @return void
     */
    public function testSyncCardsNoFiles()
    {
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('getFiles')->andReturn(collect([]))->once();
        });
        $result = app(GoogleIntegration::class)->syncCards(1);
        $this->assertFalse($result);
    }

    /**
     * Fail saving thumbnail.
     *
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     *
     * @return void
     */
    public function testSaveThumbnailFail()
    {
        list($user, $card, $file) = $this->getSetup();
        $file->thumbnailLink = '';
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, $file);
        $this->assertFalse($result);
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, false);
        $this->assertFalse($result);
    }

    /**
     * Try to save a thumbnail with no thumbnail from google.
     *
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     *
     * @return void
     */
    public function testSaveThumbnailNoAccess()
    {
        list($user, $card, $file) = $this->getSetup();
        $file->thumbnailLink = 'https://mycoolpage.com/thing.jpg';
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('getThumbnail')->andReturn(collect([]))->once();
        });
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, $file);
        $this->assertFalse($result);
    }

    /**
     * Successfull save google thumbnail.
     *
     * @throws OauthUnauthorizedRequest
     * @throws OauthMissingTokens
     *
     * @return void
     */
    public function testSaveThumbnailSuccess()
    {
        $imageName = '/myCoolImage.jpg';
        $myCoolImage = 'https://mycoolimage.com'.$imageName;
        list($user, $card, $file) = $this->getSetup();
        $file->thumbnailLink = $myCoolImage;
        $imageWidth = 200;
        $imageHeight = 400;
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($imageWidth, $imageHeight) {
            $mock->shouldReceive('getThumbnail')->andReturn(collect([
                'extension' => 'jpg',
                'thumbnail' => 'coolimagestuff',
                'width'     => $imageWidth,
                'height'    => $imageHeight,
            ]))->once();
        });
        Storage::shouldReceive('put')->andReturn(true)->once();
        Storage::shouldReceive('url')->andReturn($imageName)->once();
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, $file);
        $this->assertTrue($result);
        $this->assertDatabaseHas('cards', [
            'id'           => $card->id,
            'image'        => Config::get('app.url').$imageName,
            'image_width'  => $imageWidth,
            'image_height' => $imageHeight,
        ]);
    }

    public function testSavePermissions()
    {
        $user = $this->syncDomains();
        list($user, $card, $file) = $this->getSetup($user);
        $file->setPermissions([
            ['type' => 'anyone'],
            ['type' => 'user'],
            ['type' => 'domain', 'domain' => 'trytrig.com'],
        ]);
        $this->partialMock(LinkShareSettingRepository::class, function ($mock) {
            $mock->shouldReceive('createPublicIfNew')->once();
            $mock->shouldReceive('createAnyoneOrganizationIfNew')->once();
        });
        $this->partialMock(PermissionRepository::class, function ($mock) {
            $mock->shouldReceive('createEmail')->once();
        });
        $this->partialMock(CardRepository::class, function ($mock) {
            $mock->shouldReceive('removeAllPermissions')->once();
        });
        app(GoogleIntegration::class)->savePermissions($user, $card, $file);
    }

    /**
     * Don't save permission if it's a domain we don't recognize.
     */
    public function testSavePermissionNoDomain()
    {
        $user = $this->syncDomains();
        list($user, $card, $file) = $this->getSetup($user);
        $file->setPermissions([
            ['type' => 'domain', 'domain' => 'dexio.com'],
        ]);

        app(GoogleIntegration::class)->savePermissions($user, $card, $file);

        $shareType = LinkShareTypeRepository::ANYONE_ORGANIZATION;
        $linkShareType = app(LinkShareTypeRepository::class)->get($shareType);

        $this->assertDatabaseMissing('link_share_settings', [
            'link_share_type_id' => $linkShareType->id,
            'shareable_type'     => 'App\\Models\\Card',
            'shareable_id'       => $card->id,
        ]);
    }

    public function testSaveCardData()
    {
        $card = Card::find(1);
        Queue::fake();
        $this->createOauthConnection($card->user()->first());
        $googleServiceMock = $this->mock(GoogleServiceDrive::class);
        $fileResource = $this->mock(GoogleServiceDriveFiles::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn(new FakeContent())->once();
        });
        $googleServiceMock->files = $fileResource;
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($googleServiceMock) {
            $mock->shouldReceive('getDriveService')->andReturn($googleServiceMock)->once();
        });

        $cardData = (new ExtractDataHelperTest())->getMockDataResult('my cool content');
        $this->mock(ExtractDataHelper::class, function ($mock) use ($cardData) {
            $mock->shouldReceive('getFileData')->andReturn($cardData)->once();
        });

        $cardData->put('created', Carbon::create($cardData->get('created'))->toDateTimeString());
        $cardData->put('modified', Carbon::create($cardData->get('modified'))->toDateTimeString());
        $cardData->put('print_date', Carbon::create($cardData->get('print_date'))->toDateTimeString());
        $cardData->put('save_date', Carbon::create($cardData->get('save_date'))->toDateTimeString());
        $content = $cardData->get('content');

        app(GoogleIntegration::class)->saveCardData($card);

        $cardData->forget('content');
        $cardData = $cardData->reject(function ($value) {
            return ! $value;
        });

        $this->assertDatabaseHas('cards', [
            'content'    => $content,
            'properties' => json_encode($cardData->toArray()),
        ]);
        Queue::assertPushed(CardDedupe::class, 1);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function testSaveCardDataGoogle()
    {
        $card = Card::find(1);
        $this->createOauthConnection($card->user()->first());
        $googleServiceMock = $this->mock(GoogleServiceDrive::class);
        $fileResource = $this->mock(GoogleServiceDriveFiles::class, function ($mock) {
            $mock->shouldReceive('export')->andReturn(new FakeContent())->once();
        });
        $googleServiceMock->files = $fileResource;
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($googleServiceMock) {
            $mock->shouldReceive('getDriveService')->andReturn($googleServiceMock)->once();
        });

        $this->mock(ExtractDataHelper::class, function ($mock) {
            $mock->shouldReceive('getFileData')->withArgs(['text/plain', Mockery::any()])->andReturn(collect([]))->once();
        });

        $googleDocCardType = app(CardTypeRepository::class)
            ->firstOrCreate('application/vnd.google-apps.document')->id;

        $card->update(['card_type_id' => $googleDocCardType]);

        app(GoogleIntegration::class)->saveCardData($card);

        $this->refreshDb();
    }
}
