<?php

namespace Laravel\Nova\Fields;

use Illuminate\Http\Request;

class MorphToActionTarget extends MorphTo
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'morph-to-action-target-field';

    /**
     * Determine if the field is not redundant.
     *
     * @return bool
     */
    public function isNotRedundant(Request $request)
    {
        return true;
    }
}
