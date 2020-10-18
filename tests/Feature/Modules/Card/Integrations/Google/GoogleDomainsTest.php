<?php

namespace Tests\Feature\Modules\Card\Integrations\Google;

use App\Models\User;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Integrations\Google\GoogleDomains;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use Tests\Feature\Modules\Card\Integrations\Fakes\DomainFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class GoogleDomainsTest extends TestCase
{
    use CreateOauthConnection;
    public const DOMAIN_NAMES = ['trytrig.com', 'yourmusiclessons.com'];

    /**
     * @param bool $domains
     *
     * @throws JsonException
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     *
     * @return User|User[]|Collection|Model|null
     */
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
        $this->partialMock(GoogleDomains::class, static function ($mock) use ($fakeDomains) {
            $mock->shouldReceive('getDomains')->andReturn($fakeDomains)->once();
        });

        app(GoogleDomains::class)->syncDomains($user);

        return $user;
    }

    /**
     * @throws JsonException
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function testSyncDomains(): void
    {
        $this->syncDomains();
        $domains = [];
        foreach (self::DOMAIN_NAMES as $domain) {
            $domains[] = [$domain => true];
        }
        $this->assertDatabaseHas('users', [
            'id'         => 1,
            'properties' => $this->castToJson(['google_domains' => $domains]),
        ]);
        $this->refreshDb();
    }

    /**
     * @throws JsonException
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     * @throws OauthUnauthorizedRequest
     */
    public function testSyncDomainsNoDomains(): void
    {
        $this->syncDomains(false);
        $domains = [];
        foreach (self::DOMAIN_NAMES as $domain) {
            $domains[] = [$domain => true];
        }
        $this->assertDatabaseMissing('users', [
            'id'         => '1',
            'properties' => $this->castToJson(['google_domains' => $domains]),
        ]);
    }
}
