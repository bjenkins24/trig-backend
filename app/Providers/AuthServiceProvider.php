<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        Passport::routes();

        Gate::define('can-impersonate', static function ($user, $userToBeImpersonated) {
            $allowedUsers = Config::get('auth.admin_access');

            if (null === $allowedUsers) {
                return false;
            }

            return in_array($user->email, $allowedUsers, true)
                && ! in_array($userToBeImpersonated->email, $allowedUsers, true);
        });
    }
}
