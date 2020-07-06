<?php

namespace App\Modules\OauthIntegration;

use App\Models\OauthIntegration;

class OauthIntegrationRepository
{
    public function findByName(string $name): ?OauthIntegration
    {
        return OauthIntegration::where('name', $name)->first();
    }

    public function firstOrCreate(array $conditions): OauthIntegration
    {
        return OauthIntegration::firstOrCreate($conditions);
    }
}
