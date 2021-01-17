<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class FailingAction extends Action implements ShouldQueue
{
    use InteractsWithQueue;

    public static $failedForUser = false;

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
     * Perform the action on the given models.
     *
     * @return string|void
     */
    public function handleForUsers(ActionFields $fields, Collection $models)
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
    public function failedForUsers(ActionFields $fields, Collection $models, $e)
    {
        static::$failedForUser = true;
    }
}
