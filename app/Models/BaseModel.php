<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;

/**
 * BaseModel.
 *
 * @mixin Eloquent
 */
abstract class BaseModel extends Model
{
    use Cachable;
}
