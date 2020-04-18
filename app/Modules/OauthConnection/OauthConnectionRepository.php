<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthConnection;
use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OauthConnectionRepository
{
    public OauthIntegrationRepository $oauthIntegration;

    public function __construct(OauthIntegrationRepository $oauthIntegration)
    {
        $this->oauthIntegration = $oauthIntegration;
    }

    /**
     * Find a connection for a user.
     */
    public function findUserConnection(User $user, string $integration): OauthConnection
    {
        $oauthIntegration = $this->oauthIntegration->findByName($integration);

        return $oauthIntegration->oauthConnections()->where('user_id', $user->id)->first();
    }

    /**
     * Create a new connection.
     */
    public function create(User $user, string $integration, Collection $authConnection): OauthConnection
    {
        if (! $authConnection->has(['access_token', 'refresh_token', 'expires_in'])) {
            throw new OauthMissingTokens('A token from the oauth authentication process was not present. The oauth connection failed.');
        }
        $oauthIntegration = $this->oauthIntegration->firstOrCreate(['name' => $integration]);

        return OauthConnection::create([
            'user_id'              => $user->id,
            'oauth_integration_id' => $oauthIntegration->id,
            'access_token'         => $authConnection->get('access_token'),
            'refresh_token'        => $authConnection->get('refresh_token'),
            'expires'              => Carbon::now()->addSeconds($authConnection->get('expires_in')),
        ]);
    }

    /**
     * Check if the access token is expired.
     *
     * @return bool
     */
    public function isExpired(OauthConnection $oauthConnection): boolean
    {
        return $oauthConnection->expires->isBefore(Carbon::now());
    }
}
