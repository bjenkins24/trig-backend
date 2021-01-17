<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class FailingPivotAction extends Action implements ShouldQueue
{
    use InteractsWithQueue;

    public static $failedForRoleAssignment = false;

    /**
     * Perform the action on the given models.
     *
     * @return string|void
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $this->fail();
    }

    /**
     * Handle an action failure.
     *
     * @param \Throwable $e
     *
     * @return string|void
     */
    public function failedForRoleAssignments(ActionFields $fields, Collection $models, $e)
    {
        static::$failedForRoleAssignment = true;
    }
}
