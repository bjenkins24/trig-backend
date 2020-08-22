<?php

namespace App\Models;

use App\Support\Traits\Relationships\HasLinkShareSettings;

/**
 * App\Models\LinkShareType.
 *
 * @property int                                                                     $id
 * @property string                                                                  $name
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\LinkShareSetting[] $linkShareSettings
 * @property int|null                                                                $link_share_settings_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel disableCache()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\LinkShareType newModelQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\LinkShareType newQuery()
 * @method static \GeneaLabs\LaravelModelCaching\CachedBuilder|\App\Models\LinkShareType query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin \Eloquent
 */
class LinkShareType extends BaseModel
{
    use HasLinkShareSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];
}
