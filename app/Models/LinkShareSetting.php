<?php

namespace App\Models;

use App\Support\Traits\Relationships\BelongsToCapability;
use App\Support\Traits\Relationships\BelongsToLinkShareType;
use App\Support\Traits\Relationships\PermissionTypeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\LinkShareSetting.
 *
 * @property int                                           $id
 * @property string                                        $shareable_type
 * @property int                                           $shareable_id
 * @property int                                           $link_share_type_id
 * @property int                                           $capability_id
 * @property \Illuminate\Support\Carbon|null               $created_at
 * @property \Illuminate\Support\Carbon|null               $updated_at
 * @property \App\Models\Capability                        $capability
 * @property \App\Models\LinkShareType                     $linkShareType
 * @property \Illuminate\Database\Eloquent\Model|\Eloquent $linkShareable
 * @property \App\Models\PermissionType|null               $permissionType
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereCapabilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereLinkShareTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereShareableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereShareableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\LinkShareSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LinkShareSetting extends Model
{
    use PermissionTypeable;
    use BelongsToCapability;
    use BelongsToLinkShareType;

    /**
     * Get the owning linkshareable model.
     */
    public function linkShareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['link_share_type_id', 'capability_id'];
}
