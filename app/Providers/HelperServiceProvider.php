<?php

namespace App\Providers;

use App\Utils\ExtractDataHelper;
use App\Utils\TikaWebClient\TikaWebClientWrapper;
use Illuminate\Support\ServiceProvider;

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
            return new ExtractDataHelper(new TikaWebClientWrapper());
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
