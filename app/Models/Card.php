<?php

namespace App\Models;

use App\Modules\Card\CardRepository;
use App\Support\Traits\Relationships\BelongsToCardType;
use App\Support\Traits\Relationships\BelongsToUser;
use App\Support\Traits\Relationships\HasCardDuplicates;
use App\Support\Traits\Relationships\HasCardFavorite;
use App\Support\Traits\Relationships\HasCardIntegration;
use App\Support\Traits\Relationships\LinkShareable;
use App\Support\Traits\Relationships\Permissionables;
use ElasticScoutDriverPlus\CustomSearch;
use Eloquent;
use GeneaLabs\LaravelModelCaching\CachedBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

/**
 * App\Models\Card.
 *
 * @property int                                $id
 * @property int                                $user_id
 * @property int                                $card_type_id
 * @property string                             $title
 * @property string|null                        $description
 * @property string|null                        $content
 * @property string|null                        $image
 * @property int|null                           $image_height
 * @property int|null                           $image_width
 * @property string                             $url
 * @property Carbon                             $actual_created_at
 * @property Carbon                             $actual_modified_at
 * @property Collection|null                    $properties
 * @property Carbon|null                        $created_at
 * @property Carbon|null                        $updated_at
 * @property EloquentCollection|CardDuplicate[] $cardDuplicates
 * @property int|null                           $card_duplicates_count
 * @property CardFavorite|null                  $cardFavorite
 * @property CardIntegration|null               $cardIntegration
 * @property CardType                           $cardType
 * @property LinkShareSetting|null              $linkShareSetting
 * @property EloquentCollection|Permission[]    $permissions
 * @property int|null                           $permissions_count
 * @property CardDuplicate|null                 $primaryDuplicate
 * @property User                               $user
 *
 * @method static Builder|BaseModel disableCache()
 * @method static CachedBuilder|Card newModelQuery()
 * @method static CachedBuilder|Card newQuery()
 * @method static CachedBuilder|Card query()
 * @method static Builder|Card whereActualCreatedAt($value)
 * @method static Builder|Card whereActualModifiedAt($value)
 * @method static Builder|Card whereCardTypeId($value)
 * @method static Builder|Card whereContent($value)
 * @method static Builder|Card whereCreatedAt($value)
 * @method static Builder|Card whereDescription($value)
 * @method static Builder|Card whereId($value)
 * @method static Builder|Card whereImage($value)
 * @method static Builder|Card whereImageHeight($value)
 * @method static Builder|Card whereImageWidth($value)
 * @method static Builder|Card whereProperties($value)
 * @method static Builder|Card whereTitle($value)
 * @method static Builder|Card whereUpdatedAt($value)
 * @method static Builder|Card whereUrl($value)
 * @method static Builder|Card whereUserId($value)
 * @method static Builder|BaseModel withCacheCooldownSeconds($seconds = null)
 * @mixin Eloquent
 */
class Card extends BaseModel
{
    use BelongsToUser;
    use BelongsToCardType;
    use HasCardFavorite;
    use HasCardIntegration;
    use HasCardDuplicates;
    use Permissionables;
    use LinkShareable;
    use Searchable;
    use CustomSearch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'card_type_id',
        'title',
        'description',
        'image',
        'actual_created_at',
        'actual_modified_at',
        'url',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'actual_created_at'         => 'datetime',
        'actual_modified_at'        => 'datetime',
        'properties'                => 'collection',
    ];

    /**
     * The fields index by elastic search.
     */
    public function toSearchableArray(): array
    {
        $cardRepo = app(CardRepository::class);
        $permissions = $cardRepo->denormalizePermissions($this)->toArray();
        $cardDuplicateIds = $cardRepo->getDuplicateIds($this);

        $docTitle = null;
        if ($this->properties) {
            $docTitle = $this->properties->get('title');
        }
        $organization = $this->user()->first()->organizations()->first();
        $organizationId = null;
        if ($organization) {
            $organizationId = $organization->id;
        }

        return [
            'user_id'            => $this->user_id,
            'card_type_id'       => $this->card_type_id,
            'organization_id'    => $organizationId,
            'title'              => $this->title,
            'doc_title'          => $docTitle,
            'content'            => $this->content,
            'permissions'        => $permissions,
            'actual_created_at'  => $this->actual_created_at,
            'card_duplicate_ids' => $cardDuplicateIds,
        ];
    }
}
