<?php

namespace App\Modules\OauthIntegration;

use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFoundException;

class OauthIntegrationService
{
    public OauthIntegrationRepository $repo;

    public function __construct(OauthIntegrationRepository $repo)
    {
        $this->repo = $repo;
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
        } catch (Exception $e) {
            throw new OauthIntegrationNotFoundException("The integration key \"$integration\" is not valid. Please check the name and try again.");
        }
    }
}
