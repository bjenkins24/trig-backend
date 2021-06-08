<?php

namespace App\Modules\Capability;

use App\Models\Capability;

class CapabilityRepository
{
    /**
     * Get a Capability.
     */
    public function get(string $name): Capability
    {
        return Capability::where(['name' => $name])->firstOrFail();
    }
}
