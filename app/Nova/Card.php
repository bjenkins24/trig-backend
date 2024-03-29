<?php

namespace App\Nova;

use App\Modules\CardTag\CardTagRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Heading;
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
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'title', 'description', 'content', 'url', 'user',
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
            BelongsTo::make('User')->hideFromIndex()->sortable(),
            HasMany::make('CardTags')->hideFromIndex()->sortable(),

            // Title
            Text::make('Title')->displayUsing(static function ($name) {
                return Str::limit($name, 30);
            })->sortable()->onlyOnIndex(),
            Text::make('Title')->hideFromIndex(),
            Textarea::make('Description')->alwaysShow(),
            Textarea::make('Content'),
            Text::make('Tags', function () {
                $tagList = '';
                $tags = app(CardTagRepository::class)->getTags($this);
                foreach ($tags as $tagKey => $tag) {
                    if (0 === $tagKey) {
                        $tagList .= $tag;
                    } else {
                        $tagList .= ', '.$tag;
                    }
                }

                return $tagList;
            }),
            Text::make('Hypernyms', function () {
                $tagList = '';
                $tags = app(CardTagRepository::class)->getHypernyms($this);
                foreach ($tags as $tagKey => $tag) {
                    if (0 === $tagKey) {
                        $tagList .= $tag;
                    } else {
                        $tagList .= ', '.$tag;
                    }
                }

                return $tagList;
            }),

            // URL
            Text::make('Url')->displayUsing(static function ($url) {
                return '<a href="'.$url.'" target="_blank" rel="noreferrer">'.Str::limit($url, 45).'</a>';
            })->asHtml()->onlyOnIndex(),
            Text::make('Url')->displayUsing(static function ($url) {
                return '<a href="'.$url.'" target="_blank" rel="noreferrer">'.$url.'</a>';
            })->asHtml()->hideFromIndex(),

            Number::make('Favorites', 'total_favorites'),
            Number::make('Views', 'total_views'),
            Code::make('Properties')->json(),

            Heading::make('Timestamps'),
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
