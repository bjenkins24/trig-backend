<?php

namespace App\Models;

use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Person.
 *
 * @property int                             $id
 * @property string                          $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Models\PermissionType|null $permissionType
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Person whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Person extends Model
{
    use PermissionTypeable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['email'];
}
