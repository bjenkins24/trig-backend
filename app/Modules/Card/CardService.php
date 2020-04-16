<?php

namespace App\Modules\Card;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\OauthIntegration\OauthIntegrationService;

class CardService
{
    /**
     * @var OauthIntegrationService
     */
    private OauthIntegrationService $oauthIntegration;

    /**
     * Make card service.
     */
    public function __construct(OauthIntegrationService $oauthIntegration)
    {
        $this->oauthIntegration = $oauthIntegration;
    }

    /**
     * Make integration class.
     */
    public function makeIntegration(User $user, string $integration)
    {
        return $this->oauthIntegration->makeIntegration('App\\Modules\\Card\\Integrations', $integration);
    }

    /**
     * Sync cards for all integrations.
     *
     * @return User
     */
    public function syncAllIntegrations(User $user)
    {
        $connections = $user->oauthConnections()->get();
        foreach ($connections as $connection) {
            $integration = $connection->oauthIntegration()->first()->name;
            SyncCards::dispatch($user, $integration);
        }
    }
}
