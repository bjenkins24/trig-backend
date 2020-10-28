<?php

namespace Tests\Feature\Modules\Card\Integrations\Google;

use App\Models\Card;
use App\Models\User;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Integrations\Google\GoogleConnection;
use App\Modules\Card\Integrations\Google\GoogleDomains;
use App\Modules\Card\Integrations\Google\GoogleIntegration;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use JsonException;
use Tests\Feature\Modules\Card\Integrations\Google\Fakes\DomainFake;
use Tests\Feature\Modules\Card\Integrations\Google\Fakes\FakeGoogleServiceDrive;
use Tests\Feature\Modules\Card\Integrations\Google\Fakes\FileFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class GoogleIntegrationTest extends TestCase
{
    use CreateOauthConnection;

    public const DOMAIN_NAMES = ['trytrig.com', 'yourmusiclessons.com'];

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

    /**
     * @param bool $domains
     *
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     * @throws JsonException
     */
    public function syncDomains($domains = true): User
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
        $this->partialMock(GoogleDomains::class, static function ($mock) use ($fakeDomains) {
            $mock->shouldReceive('getDomains')->andReturn($fakeDomains)->once();
        });

        app(GoogleDomains::class)->syncDomains($user);

        return $user;
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function testDeleteTrashedCard(): void
    {
        [$user] = $this->getSetup();
        $file = new FileFake();
        $file->name = 'My cool title';
        $foreign_id = Card::find(1)->cardIntegration()->first()->foreign_id;
        $file->id = $foreign_id;
        $file->trashed = true;

        $data = app(GoogleIntegration::class)->getCardData($user, $file);

        self::assertTrue($data['data']['delete']);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function testNoFolderSync(): void
    {
        [$user] = $this->getSetup();
        $file = new FileFake();
        $file->mimeType = 'application/vnd.google-apps.folder';
        $cardData = app(GoogleIntegration::class)->getCardData($user, $file);
        self::assertEmpty($cardData);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function testGetCardData(): void
    {
        [$user] = $this->getSetup();
        $file = new FileFake();
        $googleIntegration = app(GoogleIntegration::class);
        $thumbnailLink = $googleIntegration->getThumbnailLink($user, $file);
        $cardData = $googleIntegration->getCardData($user, $file);
        self::assertEquals([
            'user_id'            => $user->id,
            'delete'             => $file->trashed,
            'card_type'          => $file->mimeType,
            'url'                => $file->webViewLink,
            'foreign_id'         => $file->id,
            'title'              => $file->name,
            'description'        => $file->description,
            'actual_created_at'  => $file->createdTime,
            'actual_modified_at' => $file->modifiedTime,
            'thumbnail_uri'      => $thumbnailLink,
        ], $cardData['data']);
        self::assertNotEmpty($cardData['permissions']);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function testGetAllCardData(): void
    {
        $this->mock(GoogleConnection::class, static function ($mock) {
            $mock->shouldReceive('getDriveService')->andReturn(new FakeGoogleServiceDrive());
        });

        $googleIntegration = app(GoogleIntegration::class);
        $cardData = $googleIntegration->getAllCardData(User::find(1), time());
        self::assertCount(2, $cardData);
    }

    /**
     * @throws JsonException
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function testGetPermissions(): void
    {
        $user = $this->syncDomains();
        $file = new FileFake();
        $file->setPermissions([
            ['type' => 'user', 'role' => 'commenter'],
            ['type' => 'anyone', 'role' => 'fileOrganizer'],
            ['type' => 'anyone', 'role' => 'organizer'],
            ['type' => 'anyone', 'role' => 'owner'],
            ['type' => 'anyone', 'role' => 'reader'],
            ['type' => 'anyone', 'role' => 'writer'],
            ['type' => 'domain', 'domain' => 'trytrig.com', 'role' => 'writer'],
            // This shouldn't save since it's not a recognized domain
            ['type' => 'domain', 'domain' => 'nowheresville.com', 'role' => 'writer'],
        ]);
        $googleIntegration = app(GoogleIntegration::class);
        $cardData = $googleIntegration->getCardData($user, $file);
        self::assertEquals([
           'users' => [
               [
                   'email'      => $file->permissions[0]->emailAddress,
                   'capability' => 'reader',
               ],
           ],
            'link_share' => [
                [
                    'type'       => 'public',
                    'capability' => 'writer',
                ],
                [
                    'type'       => 'public',
                    'capability' => 'writer',
                ],
                [
                    'type'       => 'public',
                    'capability' => 'writer',
                ],
                [
                    'type'       => 'public',
                    'capability' => 'reader',
                ],
                [
                    'type'       => 'public',
                    'capability' => 'writer',
                ],
                [
                    'type'       => 'anyone_organization',
                    'capability' => 'writer',
                ],
            ],
        ], $cardData['permissions']);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     * @throws JsonException
     */
    public function testGetFilesWithNextPage(): void
    {
        $nextPageToken = 'is_next_page';
        $user = User::find(1);
        $this->createOauthConnection($user);
        $this->mock(GoogleConnection::class, static function ($mock) {
            $mock->shouldReceive('getDriveService')->andReturn(new FakeGoogleServiceDrive());
        });

        app(GoogleIntegration::class)->getFiles($user);

        $this->assertDatabaseHas('oauth_connections', [
            'user_id'    => $user->id,
            'properties' => json_encode(['google_next_page' => $nextPageToken], JSON_THROW_ON_ERROR),
        ]);

        app(GoogleIntegration::class)->getFiles($user, time());

        $this->assertDatabaseHas('oauth_connections', [
            'user_id'    => $user->id,
            'properties' => json_encode(['google_next_page' => $nextPageToken], JSON_THROW_ON_ERROR),
        ]);
    }

    public function testGetIntegrationKey(): void
    {
        $key = GoogleIntegration::getIntegrationKey();
        self::assertEquals('google', $key);
    }
}
