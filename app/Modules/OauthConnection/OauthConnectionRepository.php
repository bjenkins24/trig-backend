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
    private OauthIntegrationRepository $oauthIntegrationRepo;

    public function __construct(OauthIntegrationRepository $oauthIntegrationRepo)
    {
        $this->oauthIntegrationRepo = $oauthIntegrationRepo;
    }

    /**
     * Find a connection for a user.
     */
    public function findUserConnection(User $user, string $integration): ?OauthConnection
    {
        $oauthIntegrationRepo = $this->oauthIntegrationRepo->findByName($integration);
        if (! $oauthIntegrationRepo) {
            return null;
        }

        return $oauthIntegrationRepo->oauthConnections()->where('user_id', $user->id)->first();
    }

    /**
     * Get integration from connection.
     */
    public function getIntegration(OauthConnection $connection): OauthIntegration
    {
        return $connection->oauthIntegration()->first();
    }

    /**
     * Create a new connection.
     *
     * @throws OauthMissingTokens
     */
    public function create(User $user, string $integration, Collection $authConnection): OauthConnection
    {
        if (! $authConnection->has(['access_token', 'refresh_token', 'expires_in'])) {
            $message = 'A token from the oauth authentication process was not present. The oauth connection failed.';
            if (env('APP_ENV', 'local')) {
                $message = 'Your access refresh or expires was missing from the response. Sometimes services like
                Google SSO will only give you the refresh token on their first connection. If your account is already
                connected to Google, you\'ll need to remove permissions from your google account in your google account
                settings before trying again';
            }
            throw new OauthMissingTokens($message);
        }
        $oauthIntegration = $this->oauthIntegrationRepo->firstOrCreate(['name' => $integration]);

        return OauthConnection::firstOrCreate([
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

    public function getAllActiveConnections(): Collection
    {
        $connections = OauthConnection::select('user_id', 'oauth_integration_id')->get();
        $integrations = OauthIntegration::select('id', 'name')->get();

        $integrations = $integrations->reduce(static function ($carry, $integration) {
            $carry[$integration->id] = $integration->name;

            return $carry;
        });

        return collect($connections->map(static function ($connection) use ($integrations) {
            return ['user_id' => $connection->user_id, 'key' => $integrations[$connection->oauth_integration_id]];
        }));
    }
}
