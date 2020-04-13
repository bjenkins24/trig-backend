<?php

namespace App\Modules\OauthConnection\Repositories;

use App\Models\OauthConnection;
use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StoreConnection
{
    /**
     * Create new oauth connection to a third party.
     *
     * @return void
     */
    public function handle(User $user, string $integration, Collection $authConnection)
    {
        if (! $authConnection->has(['access_token', 'refresh_token', 'expires_in'])) {
            throw new OauthMissingTokens('A token from the oauth authentication process was not present. The oauth connection failed.');
        }
        $oauthIntegration = OauthIntegration::firstOrCreate(['name' => $integration]);
        $oauthConnection = new OauthConnection();
        $oauthConnection->user_id = $user->id;
        $oauthConnection->oauth_integration_id = $oauthIntegration->id;
        $oauthConnection->access_token = $authConnection->get('access_token');
        $oauthConnection->refresh_token = $authConnection->get('refresh_token');
        $oauthConnection->expires = Carbon::now()->addSeconds($authConnection->get('expires_in'));

        return $oauthConnection->save();
    }
}
