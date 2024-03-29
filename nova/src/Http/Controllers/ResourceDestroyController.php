<?php

namespace Laravel\Nova\Http\Controllers;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Actionable;
use Laravel\Nova\Http\Requests\DeleteResourceRequest;
use Laravel\Nova\Nova;

class ResourceDestroyController extends Controller
{
    use DeletesFields;

    /**
     * Destroy the given resource(s).
     *
     * @return \Illuminate\Http\Response
     */
    public function handle(DeleteResourceRequest $request)
    {
        $request->chunks(150, function ($models) use ($request) {
            $models->each(function ($model) use ($request) {
                $this->deleteFields($request, $model);

                $uses = class_uses_recursive($model);

                if (in_array(Actionable::class, $uses) && ! in_array(SoftDeletes::class, $uses)) {
                    $model->actions()->delete();
                }

                $model->delete();

                tap(Nova::actionEvent(), function ($actionEvent) use ($model, $request) {
                    DB::connection($actionEvent->getConnectionName())->table('action_events')->insert(
                        $actionEvent->forResourceDelete($request->user(), collect([$model]))
                            ->map->getAttributes()->all()
                    );
                });
            });
        });

        if ($request->isForSingleResource() && ! is_null($redirect = $request->resource()::redirectAfterDelete($request))) {
            return response()->json([
                'redirect' => $redirect,
            ]);
        }
    }
}
