<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkShareSettings;
use App\Support\Traits\Relationships\HasPermissions;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Capability.
 *
 * @property int                                                                     $id
 * @property string                                                                  $name
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\LinkShareSetting[] $linkShareSettings
 * @property int|null                                                                $link_share_settings_count
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Permission[]       $permissions
 * @property int|null                                                                $permissions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Capability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Capability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Capability query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Capability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Capability whereName($value)
 * @mixin \Eloquent
 */
class Capability extends Model
{
    use HasPermissions;
    use HasLinkShareSettings;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name'];
}
