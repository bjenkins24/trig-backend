<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class NoopInlineAction extends Action
{
    use ProvidesActionFields;

    public static $applied = [];
    public static $appliedFields = [];
    public static $appliedToComments = [];

    /**
     * Perform the action on the given models.
     *
     * @return string|void
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        static::$applied[] = $models;
        static::$appliedFields[] = $fields;

        return Action::message('Hello World');
    }

    /**
     * Perform the action on the given comment models.
     *
     * @return string|void
     */
    public function handleForComments(ActionFields $fields, Collection $models)
    {
        static::$appliedFields[] = $fields;
        static::$appliedToComments[] = $models;
    }
}
