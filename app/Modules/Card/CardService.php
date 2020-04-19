<?php

namespace App\Modules\Card;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Modules\User\UserService;

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
     * @var UserService
     */
    private UserService $user;

    /**
     * Make card service.
     */
    public function __construct(
        OauthIntegrationService $oauthIntegration,
        OauthConnectionService $oauthConnection,
        UserService $user
    ) {
        $this->oauthIntegration = $oauthIntegration;
        $this->oauthConnection = $oauthConnection;
        $this->user = $user;
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
