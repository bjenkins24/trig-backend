<?php

namespace App\Modules\User;

use App\Events\User\AccountCreated;
use App\Jobs\SetupGoogleIntegration;
use App\Jobs\SyncCards;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\Card\Integrations\Google\GoogleIntegration;
use App\Modules\OauthConnection\OauthConnectionRepository;
use App\Modules\User\Helpers\ResetPasswordHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class UserService
{
    private UserRepository $userRepo;
    private OauthConnectionRepository $oauthConnectionRepo;
    public ResetPasswordHelper $resetPassword;
    private GoogleIntegration $googleIntegration;

    /**
     * Create instance of user service.
     */
    public function __construct(
        UserRepository $userRepo,
        OauthConnectionRepository $oauthConnectionRepo,
        ResetPasswordHelper $resetPassword,
        GoogleIntegration $googleIntegration
    ) {
        $this->googleIntegration = $googleIntegration;
        $this->userRepo = $userRepo;
        $this->resetPassword = $resetPassword;
        $this->oauthConnectionRepo = $oauthConnectionRepo;
    }

    public function create(array $input): User
    {
        $user = $this->userRepo->create($input);
        event(new AccountCreated($user));

        return $user;
    }

    /**
     * Get an access token to login a user in directly.
     */
    public function getAccessToken(User $user): string
    {
        return $user->createToken('trig')->accessToken;
    }

    /**
     * Create a user from a successful google SSO response
     * this will also do the initial syncCards request.
     *
     * @throws OauthMissingTokens
     */
    public function createFromGoogle(array $authParams, Collection $oauthCredentials): User
    {
        $user = $this->create($authParams);
        $workspace = $user->workspaces()->first();
        $this->oauthConnectionRepo->create($user, $workspace, $this->googleIntegration::getIntegrationKey(), $oauthCredentials);

        SetupGoogleIntegration::dispatch($user, $workspace);

        return $user;
    }

    /**
     * Get user's full name.
     */
    public function getName(User $user): string
    {
        if (! $user->first_name || ! $user->last_name) {
            $email = explode('@', $user->email);

            return sprintf(
                '%s (at) %s',
                Arr::get($email, '0'),
                Arr::get($email, '1')
            );
        }

        return $user->first_name.' '.$user->last_name;
    }

    /**
     * Sync cards for all integrations.
     */
    public function syncAllIntegrations(User $user, Workspace $workspace): void
    {
        $connections = $this->userRepo->getAllOauthConnections($user);
        foreach ($connections as $connection) {
            $integration = $this->oauthConnectionRepo->getIntegration($connection)->name;
            SyncCards::dispatch($user->id, $workspace->id, $integration)->onQueue('sync-cards');
        }
    }
}
