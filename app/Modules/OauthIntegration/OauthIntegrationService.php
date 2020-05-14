<?php

namespace App\Modules\OauthIntegration;

use App\Modules\OauthConnection\Interfaces\OauthConnectionInterface;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;

class OauthIntegrationService
{
    public function makeConnectionIntegration(string $integration): OauthConnectionInterface
    {
        return $this->makeIntegration(
            'App\\Modules\\OauthConnection\\Connections',
            $integration,
            'connection'
        );
    }

    /**
     * Make card integration class.
     */
    public function makeCardIntegration(string $integration)
    {
        return $this->makeIntegration(
            'App\\Modules\\Card\\Integrations',
            $integration,
            'integration'
        );
    }

    /**
     * Make an integration class using the fully qualified path.
     *
     * @return void
     */
    public function makeIntegration(string $path, string $integration, string $type)
    {
        $className = \Str::studly($integration);
        $classType = \Str::ucfirst($type);
        $fullClassPath = "{$path}\\{$className}{$classType}";
        try {
            return app($fullClassPath);
        } catch (\Exception $e) {
            throw new OauthIntegrationNotFound("The integration key \"$integration\" is not valid. Please check the name and try again.");
        }
    }
}
