<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Http\Request;
use Laravel\Nova\Resource;

class ForbiddenUserResource extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \Laravel\Nova\Tests\Fixtures\User::class;

    /**
     * Indicates if the resource should be globally searchable.
     *
     * @var bool
     */
    public static $globallySearchable = false;

    /**
     * Determine if the resource should be displayed for the given request.
     *
     * @return bool
     */
    public static function authorizedToViewAny(Request $request)
    {
        return $_SERVER['nova.authorize.forbidden-users'] ?? false;
    }

    /**
     * Determine if the resource should be authorized.
     *
     * @return bool
     */
    public static function authorizable()
    {
        return true;
    }

    /**
     * Get the lenses available on the resource.
     *
     * @return array
     */
    public function lenses(Request $request)
    {
        return [new UserLens()];
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [];
    }

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
    {
        return 'forbidden-users';
    }
}
