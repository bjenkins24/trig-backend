<?php

namespace App\Modules\OauthConnection;

use App\Models\OauthConnection;
use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\Card\Integrations\GoogleIntegration;
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
    public function findUserConnection(User $user, string $integration): ?OauthConnection
    {
        $oauthIntegration = $this->oauthIntegration->findByName($integration);
        if (! $oauthIntegration) {
            return null;
        }

        return $oauthIntegration->oauthConnections()->where('user_id', $user->id)->first();
    }

    /**
     * Get integration from connection.
     *
     * @return void
     */
    public function getIntegration(OauthConnection $connection): OauthIntegration
    {
        return $connection->oauthIntegration()->first();
    }

    /**
     * Create a new connection.
     */
    public function create(User $user, string $integration, Collection $authConnection): OauthConnection
    {
        if (! $authConnection->has(['access_token', 'refresh_token', 'expires_in'])) {
            $message = 'A token from the oauth authentication process was not present. The oauth connection failed.';
            if (env('APP_ENV', 'local')) {
                $message = 'Your access refresh or expires was missing from the response. Sometimes services like
                Google SSO will only give you the refresh token on their first connection. If your account is already 
                connected to Google you\'ll need to remove permissions from your google account in your google account
                settings before trying again';
            }
            throw new OauthMissingTokens($message);
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
     */
    public function isExpired(OauthConnection $oauthConnection): bool
    {
        return $oauthConnection->expires->isBefore(Carbon::now());
    }

    public function saveGoogleNextPageToken(OauthConnection $oauthConnection, ?string $nextPageToken): bool
    {
        $oauthConnection->properties = [GoogleIntegration::NEXT_PAGE_TOKEN_KEY => $nextPageToken];

        return $oauthConnection->save();
    }
}
