<?php

namespace App\Modules\OauthIntegration;

use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\Card\Interfaces\ConnectionInterface;
use App\Modules\Card\Interfaces\ContentInterface;
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
     * @throws OauthIntegrationNotFound
     */
    public function makeCardContentIntegration(string $integration): ContentInterface
    {
        return $this->makeIntegration(
            'App\\Modules\\Card\\Integrations',
            $integration,
            'content'
        );
    }

    public function isIntegrationValid(string $integration): bool
    {
        $className = Str::studly($integration);
        $fullClassPath = "App\\Modules\\Card\\Integrations\\{$className}\\{$className}Content";

        return class_exists($fullClassPath);
    }

    /**
     *  Make an integration class using the fully qualified path.
     *
     * @throws OauthIntegrationNotFound
     *
     * @return IntegrationInterface|ConnectionInterface|ContentInterface
     */
    public function makeIntegration(string $path, string $integration, string $type)
    {
        $className = Str::studly($integration);
        $classType = Str::ucfirst($type);
        $fullClassPath = "{$path}\\{$className}\\{$className}{$classType}";
        try {
            return app($fullClassPath);
        } catch (Exception $e) {
            throw new OauthIntegrationNotFound("There was an error initializing \"$integration\". Please check the name and try again.: $e");
        }
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function makeSyncCards(string $integration): SyncCardsIntegration
    {
        $syncCardsIntegration = app(SyncCardsIntegration::class);
        $syncCardsIntegration->setIntegration(
            $this->makeCardIntegration($integration),
            $this->makeCardContentIntegration($integration)
        );

        return $syncCardsIntegration;
    }
}
