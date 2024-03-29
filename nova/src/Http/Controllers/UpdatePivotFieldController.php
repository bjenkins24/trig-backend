<?php

namespace Laravel\Nova\Http\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Nova\Http\Requests\NovaRequest;

class UpdatePivotFieldController extends Controller
{
    /**
     * List the pivot fields for the given resource and relation.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(NovaRequest $request)
    {
        $model = $request->findModelOrFail();

        $accessor = $model->{$request->viaRelationship}()->getPivotAccessor();

        $model->setRelation(
            $accessor,
            $model->{$request->viaRelationship}()->withoutGlobalScopes()->findOrFail($request->relatedResourceId)->{$accessor}
        );

        return response()->json([
            'title'  => $request->newResourceWith($model)->title(),
            'fields' => $request->newResourceWith($model)->updatePivotFields(
                $request,
                $request->relatedResource
            )->all(),
        ]);
    }
}
