<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Jobs\CardDedupe;
use App\Jobs\SaveCardData;
use App\Jobs\SyncCards;
use App\Models\Card;
use App\Models\CardType;
use App\Models\OauthConnection;
use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\Card\Integrations\GoogleIntegration;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use App\Modules\Permission\PermissionRepository;
use App\Modules\User\UserRepository;
use App\Utils\ExtractDataHelper;
use Google_Service_Drive as GoogleServiceDrive;
use Google_Service_Drive_Resource_Files as GoogleServiceDriveFiles;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Feature\Modules\Card\Integrations\Fakes\DomainFake;
use Tests\Feature\Modules\Card\Integrations\Fakes\FileFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;
use Tests\Utils\ExtractDataHelperTest;

class GoogleIntegrationTest extends TestCase
{
    use CreateOauthConnection;

    const DOMAIN_NAMES = ['trytrig.com', 'yourmusiclessons.com'];

    private function getSetup(?User $user = null)
    {
        if (! $user) {
            $user = User::find(1);
            $this->createOauthConnection($user);
        }
        $card = factory(Card::class)->create([
            'user_id' => $user->id,
        ]);
        $file = new FileFake();

        return [$user, $card, $file];
    }

    public function syncDomains($domains = true)
    {
        if ($domains) {
            $domain = new DomainFake();
            $domain->isPrimary = false;
            $domain->domainName = self::DOMAIN_NAMES[1];
            $fakeDomains = [new DomainFake(), $domain];
        } else {
            $fakeDomains = [];
        }

        $user = User::find(1);
        $this->createOauthConnection($user);
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($fakeDomains) {
            $mock->shouldReceive('getDomains')->andReturn($fakeDomains)->once();
        });

        app(GoogleIntegration::class)->syncDomains($user);

        return $user;
    }

    /**
     * Test syncing domains.
     *
     * @return void
     */
    public function testSyncDomains()
    {
        $this->syncDomains();
        $domains = [];
        foreach (self::DOMAIN_NAMES as $domain) {
            $domains[] = [$domain => true];
        }
        $this->assertDatabaseHas('users', [
            'id'         => '1',
            'properties' => json_encode(['google_domains' => $domains]),
        ]);
        $this->refreshDb();
    }

    /**
     * Test syncing domains.
     *
     * @return void
     */
    public function testSyncDomainsNoDomains()
    {
        $this->syncDomains(false);
        $domains = [];
        foreach (self::DOMAIN_NAMES as $domain) {
            $domains[] = [$domain => true];
        }
        $this->assertDatabaseMissing('users', [
            'id'         => '1',
            'properties' => json_encode(['google_domains' => $domains]),
        ]);
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncCardsContinue()
    {
        $user = $this->syncDomains();
        $file = new FileFake();
        $file->name = 'My cool title';
        $file->id = 'fakeid';
        $nextPageToken = 'next_page_token';
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($file, $nextPageToken) {
            $mock->shouldReceive('saveThumbnail')->twice();
            $mock->shouldReceive('savePermissions')->twice();
            $mock->shouldReceive('getNewNextPageToken')->andReturn($nextPageToken)->once();
            $mock->shouldReceive('listFilesFromService')->andReturn(collect([new FileFake(), $file]))->once();
        });

        \Queue::fake();

        $result = app(GoogleIntegration::class)->syncCards($user);

        \Queue::assertPushed(SyncCards::class, 1);
        \Queue::assertPushed(SaveCardData::class, 2);

        $card = Card::where(['title', $file->name]);
        $cardType = CardType::where(['name' => $file->mimeType])->first();

        $this->assertDatabaseHas('cards', [
            'card_type_id'       => $cardType->id,
            'title'              => $file->name,
            'description'        => $file->description,
            'url'                => $file->webViewLink,
            'actual_created_at'  => Carbon::create($file->createdTime)->toDateTimeString(),
            'actual_modified_at' => Carbon::create($file->modifiedTime)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'foreign_id' => $file->id,
        ]);
        $this->refreshDb();
    }

    private function syncCardsFail($file)
    {
        $user = User::find(1);
        $this->createOauthConnection($user);
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($file) {
            $mock->shouldReceive('getFiles')->andReturn(collect([$file]))->once();
        });

        $result = app(GoogleIntegration::class)->syncCards($user);
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncCardsFail()
    {
        $this->partialMock(UserRepository::class, function ($mock) {
            $mock->shouldReceive('createCard')->andReturn(null)->once();
        });
        $file = new FileFake();
        $file->name = 'My failed name';
        $this->syncCardsFail($file);
        $this->assertDatabaseMissing('cards', [
            'title' => $file->name,
        ]);
    }

    /**
     * If there are no files from google we should do nothing.
     *
     * @return void
     */
    public function testSyncCardsNoFiles()
    {
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('getFiles')->andReturn(collect([]))->once();
        });
        $result = app(GoogleIntegration::class)->syncCards(User::find(1));
        $this->assertFalse($result);
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncCardsTrashed()
    {
        $file = new FileFake();
        $file->name = 'This card is super trash';
        $file->trashed = true;

        $this->syncCardsFail($file);
        $this->assertDatabaseMissing('cards', [
            'title' => $file->name,
        ]);
    }

    /**
     * Fail saving thumbnail.
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
        \Storage::shouldReceive('put')->andReturn(true)->once();
        \Storage::shouldReceive('url')->andReturn($imageName)->once();
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, $file);
        $this->assertTrue($result);
        $this->assertDatabaseHas('cards', [
            'id'           => $card->id,
            'image'        => \Config::get('app.url').$imageName,
            'image_width'  => $imageWidth,
            'image_height' => $imageHeight,
        ]);
    }

    /**
     * Save permissions.
     */
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

    public function testGetFiles()
    {
        $nextPageToken = '12345';
        $user = User::find(1);
        $this->createOauthConnection($user);
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($nextPageToken) {
            $mock->shouldReceive('getNewNextPageToken')->andReturn($nextPageToken)->twice();
            $mock->shouldReceive('listFilesFromService')->andReturn(collect([]))->twice();
        });

        app(GoogleIntegration::class)->getFiles($user);

        $this->assertDatabaseHas('oauth_connections', [
            'user_id'    => $user->id,
            'properties' => json_encode([GoogleIntegration::NEXT_PAGE_TOKEN_KEY => $nextPageToken]),
        ]);

        app(GoogleIntegration::class)->getFiles($user);
    }

    public function testSaveCardData()
    {
        $card = Card::find(1);
        \Queue::fake();
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
        \Queue::assertPushed(CardDedupe::class, 1);
    }

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

    /**
     * @dataProvider googleToMimeProvider
     */
    public function testGoogleToMime($mime, $expected)
    {
        $this->assertEquals($expected, app(GoogleIntegration::class)->googleToMime($mime));
    }

    public function googleToMimeProvider()
    {
        return [
            ['application/vnd.google-apps.audio', ''],
            ['application/vnd.google-apps.document', 'text/plain'],
            ['application/vnd.google-apps.spreadsheet', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    public function testGetCurrentNextPageToken()
    {
        $token = '123';

        OauthIntegration::create(['name' => 'google']);
        $oauthConnection = OauthConnection::create([
            'user_id'              => 1,
            'oauth_integration_id' => 1,
            'properties'           => [GoogleIntegration::NEXT_PAGE_TOKEN_KEY => $token],
        ]);
        $nextPageToken = app(GoogleIntegration::class)->getCurrentNextPageToken($oauthConnection);

        $this->assertEquals($token, $nextPageToken);

        $oauthConnection->properties = [];
        $oauthConnection->save();

        $nextPageToken = app(GoogleIntegration::class)->getCurrentNextPageToken($oauthConnection);

        $this->assertEquals($nextPageToken, null);
    }
}

class FakeContent
{
    public function getBody()
    {
        return null;
    }
}
