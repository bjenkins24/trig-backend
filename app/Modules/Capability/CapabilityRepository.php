<?php

namespace App\Modules\Capability;

use App\Models\Capability;

class CapabilityRepository
{
    /**
     * Get a Capability.
     *
     * @param [string] $Capability like reader or writer
     *
     * @return void
     */
    public function get(string $name): Capability
    {
        return Capability::where(['name' => $name])->firstOrFail();
    }
}
