<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Organization.
 *
 * @property int                                                         $id
 * @property string|null                                                 $name
 * @property \Illuminate\Support\Carbon|null                             $created_at
 * @property \Illuminate\Support\Carbon|null                             $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property int|null                                                    $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Organization whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Organization extends Model
{
    use BelongsToManyUsers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
