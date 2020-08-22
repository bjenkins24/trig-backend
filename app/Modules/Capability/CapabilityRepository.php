<?php

namespace App\Modules\Capability;

use App\Models\Capability;
use Illuminate\Database\Eloquent\Model;

class CapabilityRepository
{
    /**
     * Get a Capability.
     */
    public function get(string $name): Model
    {
        return Capability::where(['name' => $name])->firstOrFail();
    }
}
