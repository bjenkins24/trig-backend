<?php

namespace App\Modules\OauthIntegration;

use App\Models\OauthIntegration;

class OauthIntegrationRepository
{
    /**
     * Find by name.
     */
    public function findByName(string $name): OauthIntegration
    {
        return OauthIntegration::where('name', $name)->first();
    }

    /**
     * firstOrCreate.
     *
     * @param string $integration
     */
    public function firstOrCreate(array $conditions): OauthIntegration
    {
        return OauthIntegration::firstOrCreate($conditions);
    }
}
