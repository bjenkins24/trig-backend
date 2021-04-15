<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class Card extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Card::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'title', 'description', 'content', 'url',
    ];

    public static function uriKey()
    {
        return 'kard';
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make('ID')->sortable(),
            Text::make('Title')->displayUsing(static function ($name) {
                return Str::limit($name, 90);
            })->sortable(),
            Textarea::make('Description')->alwaysShow(),
            Textarea::make('Content'),
            Text::make('Url')->displayUsing(static function ($url) {
                return '<a href="'.$url.'" target="_blank" rel="noreferrer">'.Str::limit($url, 45).'</a>';
            })->asHtml(),
            Number::make('Favorites', 'total_favorites'),
            Number::make('Views', 'total_views'),
            Code::make('Properties')->json(),
            DateTime::make('Updated At')->hideFromIndex(),
            DateTime::make('Created At')->hideFromIndex(),
            DateTime::make('Actual Created At')->hideFromIndex(),
            DateTime::make('Actual Updated At')->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
