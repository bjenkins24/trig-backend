<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class UnauthorizedAction extends Action
{
    use ProvidesActionFields;

    /**
     * Perform the action on the given models.
     *
     * @return string|void
     */
    public function handle(ActionFields $fields, Collection $models)
    {
    }
}
