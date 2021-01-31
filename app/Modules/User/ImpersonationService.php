<?php

namespace App\Modules\User;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ImpersonationService
{
    public function isBeingImpersonated(User $user): bool
    {
        return null !== $user->token() && 'impersonation' === $user->token()->name;
    }

    public function hasActiveImpersonationTokens(User $user): bool
    {
        return $user->tokens()->where('name', 'impersonation')->where('revoked', 0)->count() > 0;
    }

    public function revokeImpersonation(User $user): bool
    {
        return null !== $user->token() && $user->token()->revoke();
    }

    public function revokeAllImpersonationTokens(User $user): void
    {
        $user->tokens()->where('name', 'impersonation')->each(static function ($token) {
            $token->revoke();
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function impersonate(User $user): array
    {
        if (! Gate::allows('can-impersonate', $user)) {
            throw new AuthorizationException('User not whitelisted to perform impersonation');
        }

        if ($this->isBeingImpersonated($user) || $this->hasActiveImpersonationTokens($user)) {
            $this->revokeAllImpersonationTokens($user);
        }

        $token = $user->createToken('impersonation', []);

        return [
            'access_token'  => $token->accessToken,
            'expiresIn'     => $token->token->expires_at,
            'token_type'    => 'impersonation',
            'refresh_token' => '',
        ];
    }
}
