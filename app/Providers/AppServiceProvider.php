<?php

namespace App\Providers;

use App\Models\Card;
use App\Observers\CardObserver;
use App\Utils\StrCustom;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Card::observe(CardObserver::class);
        if (DB::connection() instanceof SQLiteConnection) {
            DB::statement(DB::raw('PRAGMA foreign_keys=1'));
        }
        Str::macro('truncateOnWord', static function (string $string, int $maxChars) {
            return StrCustom::truncateOnWord($string, $maxChars);
        });
        Str::macro('purifyHtml', static function (string $string) {
            return StrCustom::purifyHtml($string);
        });
        Str::macro('htmlToMarkdown', static function (?string $string, array $tagsToRemove = []) {
            return StrCustom::htmlToMarkdown($string, $tagsToRemove);
        });
        Str::macro('htmlToText', static function (string $string, array $tagsToRemove = []) {
            return StrCustom::htmlToText($string, $tagsToRemove);
        });
        Str::macro('removeLineBreaks', static function (string $string) {
            return StrCustom::removeLineBreaks($string);
        });
        Str::macro('hasExtension', static function (string $string) {
            return StrCustom::hasExtension($string);
        });
        Str::macro('toSingleSpace', static function (string $string) {
            return StrCustom::toSingleSpace($string);
        });
        Collection::macro('recursive', function () {
            return $this->map(static function ($value) {
                if (is_array($value) || is_object($value)) {
                    return collect($value)->recursive();
                }

                return $value;
            });
        });
    }
}
