<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToManyUsers;
use App\Support\Traits\Relationships\HasCards;
use App\Support\Traits\Relationships\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Workspace.
 *
 * @property int                                                         $id
 * @property string|null                                                 $name
 * @property \Illuminate\Support\Carbon|null                             $created_at
 * @property \Illuminate\Support\Carbon|null                             $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property int|null                                                    $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Workspace whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Workspace extends Model
{
    use BelongsToManyUsers;
    use HasTags;
    use HasCards;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
