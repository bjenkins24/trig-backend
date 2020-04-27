<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Models\Card;
use App\Models\User;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\Card\Integrations\GoogleIntegration;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Card\Integrations\Fakes\DomainFake;
use Tests\Feature\Modules\Card\Integrations\Fakes\FileFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    use RefreshDatabase;
    use CreateOauthConnection;

    const DOMAIN_NAMES = ['trytrig.com', 'yourmusiclessons.com'];

    private function getThumbnailSetup(string $thumbnailLink)
    {
        $user = User::find(1);
        $this->createOauthConnection($user);
        $card = factory(Card::class)->create([
            'user_id' => $user->id,
        ]);
        $file = new FileFake();
        $file->thumbnailLink = $thumbnailLink;

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
        $this->assertDatabaseHas('users', [
            'id'         => '1',
            'properties' => json_encode(['google_domains' => self::DOMAIN_NAMES]),
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
        $fakeTitle = "Brian's Title";
        $fakeThumbnailUrl = '/storage/public/card-thumbnails/1.jpg';
        $fakeUrl = 'http://myfakeurl.example.com';
        $fakeId = 'My fake Id';
        $filePermissions = [
            ['type' => 'user', 'role' => 'reader'],
            ['type' => 'domain', 'role' => 'owner', 'domain' => 'dexio.com'],
            ['type' => 'domain', 'role' => 'fileOrganizer', 'domain' => self::DOMAIN_NAMES[0]],
            ['type' => 'anyone', 'role' => 'commenter'],
        ];
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($fakeTitle, $fakeUrl, $fakeId, $filePermissions) {
            // https://developers.google.com/drive/api/v3/ref-roles
            // Valid Roles: 'organizer', 'owner', 'fileOrganizer', 'writer', 'commenter', 'reader'
            $file = new FileFake($filePermissions);
            $file->name = $fakeTitle;
            $file->webViewLink = $fakeUrl;
            $file->id = $fakeId;

            $mock->shouldReceive('getFiles')->andReturn(collect([
                new FileFake([['type' => 'user', 'role' => 'writer']]),
                $file,
            ]))->once();

            $mock->shouldReceive('getThumbnail')
                ->andReturn(collect(['thumbnail' => 'content', 'extension' => 'jpeg']))
                ->twice();
        });

        $this->partialMock(LinkShareSettingRepository::class, function ($mock) {
            $mock->shouldReceive('createAnyoneOrganizationIfNew')->once();
        });

        \Storage::shouldReceive('put')->andReturn(true)->twice();
        \Storage::shouldReceive('url')->andReturn($fakeThumbnailUrl)->twice();

        $result = app(GoogleIntegration::class)->syncCards($user);

        $card = Card::where(['title', $fakeTitle]);

        foreach ($filePermissions as $filePermission) {
            $this->assertDatabaseHas('permissions', [
                'permissionable_type' => 'App\\Models\\Card',
                'permissionable_id'   => $card->id,
                'capability_id'       => app(CapabilityRepository::class)->get(GoogleIntegration::CAPABILITY_MAP[$filePermission['role']])->id,
            ]);
            if ('user' === $filePermission['type']) {
                // brian@trytrig.com is in the FakeFile class - this is me being lazy not referencing it
                $person = Person::where(['email' => 'brian@trytrig.com']);
                $this->assertDatabaseHas('permission_types', [
                    'typeable_type' => 'App\\Models\\Person',
                    'typeable_id'   => $person->id,
                ]);
            }
            if ('anyone' === $filePermission['type']) {
                $this->assertDatabaseHas('permission_types', [
                    'typeable_type' => null,
                    'typeable_id'   => null,
                ]);
            }
        }

        $this->assertDatabaseHas('cards', [
            'title' => $fakeTitle,
            'url'   => $fakeUrl,
            'image' => \Config::get('app.url').$fakeThumbnailUrl,
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'foreign_id' => $fakeId,
        ]);
    }

    /**
     * Fail saving thumbnail.
     *
     * @return void
     * @group n
     */
    public function testSaveThumbnailFail()
    {
        list($user, $card, $file) = $this->getThumbnailSetup('');
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, $file);
        $this->assertFalse($result);
        $result = app(GoogleIntegration::class)->saveThumbnail($user, $card, false);
        $this->assertFalse($result);
    }

    /**
     * Try to save a thumbnail with no thumbnail from google.
     *
     * @return void
     * @group n
     */
    public function testSaveThumbnailNoAccess()
    {
        list($user, $card, $file) = $this->getThumbnailSetup('https://mycoolpage.com/thing.jpg');
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
     * @group n
     */
    public function testSaveThumbnailSuccess()
    {
        $imageName = '/myCoolImage.jpg';
        $myCoolImage = 'https://mycoolimage.com'.$imageName;
        list($user, $card, $file) = $this->getThumbnailSetup($myCoolImage);
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
}
