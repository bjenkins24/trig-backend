<?php

namespace App\Support\Traits;

trait HandlesProperties
{
    public function setProperties(array $properties): void
    {
        if (empty($this->properties)) {
            $this->properties = collect($properties);

            return;
        }
        foreach ($properties as $propertyKey => $property) {
            $this->properties = $this->properties->put($propertyKey, $property);
        }
    }
}
