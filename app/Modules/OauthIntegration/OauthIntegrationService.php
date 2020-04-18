<?php

namespace App\Modules\OauthIntegration;

use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFoundException;

class OauthIntegrationService
{
    /**
     * Make an integration class using the fully qualified path.
     *
     * @return void
     */
    public function makeIntegration(string $path, string $integration)
    {
        $className = \Str::studly($integration);
        $fullClassPath = "$path\\$className";
        try {
            return app($fullClassPath);
        } catch (Exception $e) {
            throw new OauthIntegrationNotFoundException("The integration key \"$integration\" is not valid. Please check the name and try again.");
        }
    }
}
