<?php

namespace App\Providers;

use App\Modules\OauthConnection\Connections\Google;
use Google_Client as GoogleClient;
use Illuminate\Support\ServiceProvider;

class OauthConnectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Google::class, function () {
            $client = new GoogleClient();
            $client->setApplicationName('Trig');
            $client->setClientId(\Config::get('services.google.client_id'));
            $client->setClientSecret(\Config::get('services.google.client_secret'));
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            $client->setDeveloperKey(\Config::get('services.google.drive_api_key'));
            $client->setRedirectUri('http://localhost:8080');

            return new Google($client);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
