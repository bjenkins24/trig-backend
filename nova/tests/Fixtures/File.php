<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Actions\Actionable;

class File extends Model
{
    use Actionable;

    /**
     * The attributes that are guarded.
     *
     * @var array
     */
    protected $guarded = [];

    public function setFilesAttribute($value)
    {
        if (! is_string($value) || 0 !== strpos($value, 'avatars')) {
            throw new \RuntimeException('Invalid argument');
        }
    }
}
