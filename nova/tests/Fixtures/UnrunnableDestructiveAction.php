<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;

class UnrunnableDestructiveAction extends DestructiveAction
{
    use ProvidesActionFields;

    public static $applied = [];

    /**
     * Perform the action on the given models.
     *
     * @return string|void
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        static::$applied[] = $models;
    }
}
