<?php

namespace App\Modules\User;

use App\Events\User\AccountCreated;
use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\OauthConnection\Connections\GoogleConnection;
use App\Modules\OauthConnection\OauthConnectionService;
use App\Modules\User\Helpers\ResetPasswordHelper;
use Illuminate\Support\Collection;

class UserService
{
    public UserRepository $repo;
    public ResetPasswordHelper $resetPassword;
    private OauthConnectionService $oauthConnection;

    /**
     * Create instance of user service.
     */
    public function __construct(
        UserRepository $repo,
        ResetPasswordHelper $resetPassword,
        OauthConnectionService $oauthConnection
    ) {
        $this->repo = $repo;
        $this->resetPassword = $resetPassword;
        $this->oauthConnection = $oauthConnection;
    }

    public function create(array $input): User
    {
        $user = $this->repo->create($input);
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
     * @param array $response
     */
    public function createFromGoogle(array $authParams, Collection $oauthCredentials): User
    {
        $user = $this->create($authParams);
        $result = $this->oauthConnection->repo->create($user, GoogleConnection::getKey(), $oauthCredentials);

        SyncCards::dispatch($user, GoogleConnection::getKey());

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
                \Arr::get($email, '0'),
                \Arr::get($email, '1')
            );
        }

        return $user->first_name.' '.$user->last_name;
    }
}
