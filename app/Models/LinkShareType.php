<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkShareSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\LinkShareType.
 *
 * @property int                                                                     $id
 * @property string                                                                  $name
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\LinkShareSetting[] $linkShareSettings
 * @property int|null                                                                $link_share_settings_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType whereName($value)
 * @mixin \Eloquent
 */
class LinkShareType extends Model
{
    use HasLinkShareSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
