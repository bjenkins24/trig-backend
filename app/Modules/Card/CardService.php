<?php

namespace App\Modules\Card;

use App\Models\User;
use App\Modules\Card\Interfaces\IntegrationInterface;
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
    private function makeIntegration(User $user, string $integration): IntegrationInterface
    {
        return $this->oauthIntegration->makeIntegration(
            'App\\Modules\\Card\\Integrations',
            $integration,
            ['user' => $user]
        );
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
            $result = $this->makeIntegration($user, $integration)->syncCards();
            dd($result);
        }
    }
}
