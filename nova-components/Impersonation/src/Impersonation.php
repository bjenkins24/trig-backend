<?php

namespace Nova\Impersonation;

use Laravel\Nova\Fields\Field;

class Impersonation extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'impersonation';

    public function __construct($user_id)
    {
        parent::__construct(null, null, null);

        if ($user_id) {
            $this->withMeta(['user_id' => $user_id]);
        }

        $this->onlyOnIndex();
    }
}
