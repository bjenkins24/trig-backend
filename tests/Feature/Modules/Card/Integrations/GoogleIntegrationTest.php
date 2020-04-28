<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use App\Modules\Card\Integrations\GoogleIntegration;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use App\Modules\Permission\PermissionRepository;
use App\Modules\User\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Modules\Card\Integrations\Fakes\DomainFake;
use Tests\Feature\Modules\Card\Integrations\Fakes\FileFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class GoogleIntegrationTest extends TestCase
{
    use RefreshDatabase;
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

    public function syncDomains()
    {
        $user = User::find(1);
        $this->createOauthConnection($user);
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $domain = new DomainFake();
            $domain->isPrimary = false;
            $domain->domainName = self::DOMAIN_NAMES[1];

            $mock->shouldReceive('getDomains')->andReturn(collect([new DomainFake(), $domain]))->once();
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
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncCards()
    {
        $user = $this->syncDomains();
        $file = new FileFake();
        $file->name = 'My cool title';
        $file->id = 'fakeid';
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($file) {
            $mock->shouldReceive('getFiles')->andReturn(collect([$file]))->once();
            $mock->shouldReceive('saveThumbnail')->once();
            $mock->shouldReceive('savePermissions')->once();
        });

        $result = app(GoogleIntegration::class)->syncCards($user);

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
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('getThumbnail')->andReturn(collect([
                'extension' => 'jpg',
                'thumbnail' => 'coolimagestuff',
            ]))->once();
        });
        \Storage::shouldReceive('put')->andReturn(true)->once();
        \Storage::shouldReceive('url')->andReturn($imageName)->once();
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, $file);
        $this->assertTrue($result);
        $this->assertDatabaseHas('cards', [
            'id'    => $card->id,
            'image' => \Config::get('app.url').$imageName,
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
     *
     * @group n
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
}
