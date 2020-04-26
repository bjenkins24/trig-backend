<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Models\User;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\Card\Integrations\GoogleIntegration;
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
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($fakeTitle, $fakeUrl, $fakeId) {
            // https://developers.google.com/drive/api/v3/ref-roles
            // Valid Roles: 'organizer', 'owner', 'fileOrganizer', 'writer', 'commenter', 'reader'
            $file = new FileFake([
                ['type' => 'user', 'role' => 'reader'],
                ['type' => 'domain', 'role' => 'owner', 'domain' => 'dexio.com'],
                ['type' => 'domain', 'role' => 'fileOrganizer', 'domain' => self::DOMAIN_NAMES[0]],
                ['type' => 'anyone', 'role' => 'commenter'],
            ]);
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

        \Storage::shouldReceive('put')->andReturn(true)->twice();
        \Storage::shouldReceive('url')->andReturn($fakeThumbnailUrl)->twice();

        $result = app(GoogleIntegration::class)->syncCards($user);

        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => 'App\\Models\\Card',
            'permissionable_id'   => 1,
            'capability_id'       => app(CapabilityRepository::class)->get('reader')->id,
        ]);

        $this->assertDatabaseHas('cards', [
            'title' => $fakeTitle,
            'url'   => $fakeUrl,
            'image' => \Config::get('app.url').$fakeThumbnailUrl,
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'foreign_id' => $fakeId,
        ]);
    }
}
