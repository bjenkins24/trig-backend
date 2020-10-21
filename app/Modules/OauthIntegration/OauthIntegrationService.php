<?php

namespace App\Modules\OauthIntegration;

use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\Card\Interfaces\ConnectionInterface;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Exception;
use Illuminate\Support\Str;

class OauthIntegrationService
{
    /**
     * @throws OauthIntegrationNotFound
     */
    public function makeConnectionIntegration(string $integration): ConnectionInterface
    {
        return $this->makeIntegration(
            'App\\Modules\\Card\\Integrations',
            $integration,
            'connection'
        );
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function makeCardIntegration(string $integration): IntegrationInterface
    {
        return $this->makeIntegration(
            'App\\Modules\\Card\\Integrations',
            $integration,
            'integration'
        );
    }

    /**
     *  Make an integration class using the fully qualified path.
     *
     * @throws OauthIntegrationNotFound
     *
     * @return IntegrationInterface|ConnectionInterface
     */
    public function makeIntegration(string $path, string $integration, string $type)
    {
        $className = Str::studly($integration);
        $classType = Str::ucfirst($type);
        $fullClassPath = "{$path}\\{$className}\\{$className}{$classType}";
        try {
            return app($fullClassPath);
        } catch (Exception $e) {
            throw new OauthIntegrationNotFound("The integration key \"$integration\" is not valid. Please check the name and try again.");
        }
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function makeSyncCards(string $integration): SyncCardsIntegration
    {
        $syncCardsIntegration = app(SyncCardsIntegration::class);
        $syncCardsIntegration->setIntegration($this->makeCardIntegration($integration));

        return $syncCardsIntegration;
    }
}
