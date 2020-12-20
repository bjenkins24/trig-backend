<?php

namespace App\Modules\Card\Integrations\Google;

use App\Models\User;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Exception;
use Illuminate\Support\Facades\Log;
use JsonException;

class GoogleDomains
{
    private GoogleConnection $googleConnection;

    public function __construct(GoogleConnection $googleConnection)
    {
        $this->googleConnection = $googleConnection;
    }

    /**
     * If a user belongs to G Suite, then they will belong to one or more domains.
     * The domain the user belongs to will be used to decide permissions for which cards
     * a user can view.
     *
     * For example, if a user shares a card in G Drive and makes it discoverable to all users
     * on the domain yourmusiclessons.com, we should allow users in Trig to all discover that as well
     *
     * One G Suite account CAN have multiple domains: https://support.google.com/a/answer/7502379
     * Each time a connection is made we will also check their accessible domains. If there is a domain
     * that we don't recognize, we'll add it to the workspaces google domains. By default _all_
     * domains will be accessible from within Trig.
     *
     * A Trig admin will be able to select or deselect which domains their Trig account should be
     * accessible for, in the settings for Google from within Trig.
     *
     * @throws JsonException
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function getDomains(User $user): array
    {
        $service = $this->googleConnection->getDirectoryService($user);

        try {
            // my_customer get's the domains for the current customer which is what we want
            // weird API, but that's 100% Google
            return $service->domains->listDomains('my_customer')->domains;
        } catch (Exception $e) {
            $error = json_decode($e->getMessage(), true, 512, JSON_THROW_ON_ERROR);
            if (! $error || 404 !== $error->error->code) {
                Log::notice('Unable to retrieve domains for user. Error: '.json_encode($error, JSON_THROW_ON_ERROR));
            }
        }

        return [];
    }

    /**
     * @throws JsonException
     * @throws OauthIntegrationNotFound
     * @throws OauthUnauthorizedRequest
     */
    public function syncDomains(User $user): bool
    {
        $domains = $this->getDomains($user);
        if (! $domains) {
            return false;
        }
        $properties = ['google_domains' => []];
        foreach ($domains as $domain) {
            $properties['google_domains'][] = [$domain->domainName => true];
        }
        $user->properties = $properties;
        $user->save();

        return true;
    }
}
