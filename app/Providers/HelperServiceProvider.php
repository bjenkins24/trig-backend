<?php

namespace App\Providers;

use App\Utils\ExtractDataHelper;
use Illuminate\Support\ServiceProvider;
use Vaites\ApacheTika\Client as TikaClient;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ExtractDataHelper::class, function () {
            return new ExtractDataHelper(TikaClient::make(\Config::get('app.tika_url')));
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
