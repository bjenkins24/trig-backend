<?php

namespace App\Modules\Card;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\OauthIntegrationService;

class CardService
{
    /**
     * @var OauthIntegrationService
     */
    private OauthIntegrationService $oauthIntegration;

    /**
     * @var OauthConnectionService
     */
    private OauthConnectionService $oauthConnection;

    /**
     * Make card service.
     */
    public function __construct(
        OauthIntegrationService $oauthIntegration,
        OauthConnectionService $oauthConnection
    ) {
        $this->oauthIntegration = $oauthIntegration;
        $this->oauthConnection = $oauthConnection;
    }

    /**
     * Make integration class.
     */
    public function makeIntegration(string $integration)
    {
        return $this->oauthIntegration->makeIntegration(
            'App\\Modules\\Card\\Integrations',
            $integration,
            'integration'
        );
    }

    /**
     * Sync cards for all integrations.
     *
     * @return User
     */
    public function syncAllIntegrations(User $user)
    {
        $connections = $this->user->repo->getAllOauthConnections($user);
        foreach ($connections as $connection) {
            $integration = $this->oauthConnection->repo->getIntegration($connection)->name;
            SyncCards::dispatch($user, $integration);
        }
    }
}
