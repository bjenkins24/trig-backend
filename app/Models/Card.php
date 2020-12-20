<?php

namespace App\Models;

use App\Modules\Card\CardRepository;
use App\Modules\CardFavorite\CardFavoriteRepository;
use App\Modules\CardTag\CardTagRepository;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\CardView\CardViewRepository;
use App\Support\Traits\Relationships\BelongsToCardType;
use App\Support\Traits\Relationships\BelongsToUser;
use App\Support\Traits\Relationships\BelongsToWorkspace;
use App\Support\Traits\Relationships\HasCardDuplicates;
use App\Support\Traits\Relationships\HasCardFavorite;
use App\Support\Traits\Relationships\HasCardIntegration;
use App\Support\Traits\Relationships\HasCardSyncs;
use App\Support\Traits\Relationships\HasCardTags;
use App\Support\Traits\Relationships\HasCardView;
use App\Support\Traits\Relationships\LinkShareable;
use App\Support\Traits\Relationships\Permissionables;
use ElasticScoutDriverPlus\CustomSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * App\Models\Card.
 *
 * @property int                                                                  $id
 * @property int                                                                  $user_id
 * @property int                                                                  $card_type_id
 * @property string                                                               $title
 * @property string|null                                                          $description
 * @property string|null                                                          $content
 * @property string|null                                                          $image
 * @property int|null                                                             $image_height
 * @property int|null                                                             $image_width
 * @property string                                                               $url
 * @property \Illuminate\Support\Carbon                                           $actual_created_at
 * @property \Illuminate\Support\Carbon                                           $actual_updated_at
 * @property \Illuminate\Support\Collection|null                                  $properties
 * @property \Illuminate\Support\Carbon|null                                      $created_at
 * @property \Illuminate\Support\Carbon|null                                      $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\CardDuplicate[] $cardDuplicates
 * @property int|null                                                             $card_duplicates_count
 * @property \App\Models\CardFavorite|null                                        $cardFavorite
 * @property \App\Models\CardIntegration|null                                     $cardIntegration
 * @property \App\Models\CardType                                                 $cardType
 * @property \App\Models\LinkShareSetting|null                                    $linkShareSetting
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Permission[]    $permissions
 * @property int|null                                                             $permissions_count
 * @property \App\Models\CardDuplicate|null                                       $primaryDuplicate
 * @property \App\Models\User                                                     $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereActualCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereActualupdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereCardTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereImageHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereImageWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Card whereUserId($value)
 * @mixin \Eloquent
 */
class Card extends Model
{
    use BelongsToUser;
    use BelongsToWorkspace;
    use BelongsToCardType;
    use HasCardFavorite;
    use HasCardView;
    use HasCardIntegration;
    use HasCardDuplicates;
    use HasCardSyncs;
    use HasCardTags;
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
        'workspace_id',
        'card_type_id',
        'title',
        'description',
        'image',
        'actual_created_at',
        'actual_updated_at',
        'url',
        'content',
        'token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'actual_created_at'         => 'datetime',
        'actual_updated_at'         => 'datetime',
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
        $linkTypeId = app(CardTypeRepository::class)->findByName('link')->id;

        return [
            'user_id'               => $this->user_id,
            'card_type'             => app(CardTypeRepository::class)->mapCardTypeToWords($this),
            'url'                   => $this->url ?? '',
            'workspace_id'          => $this->workspace_id,
            'tags'                  => app(CardTagRepository::class)->denormalizeTags($this)->toArray(),
            'title'                 => $this->title,
            'content'               => $linkTypeId === $this->card_type_id ? Str::htmlToMarkdown($this->content, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']) : $this->content,
            'permissions'           => $permissions,
            'favorites_by_user_id'  => app(CardFavoriteRepository::class)->getUserIdsByCard($this)->toArray(),
            'views'                 => app(CardViewRepository::class)->denormalizeCardViews($this)->toArray(),
            'actual_created_at'     => $this->actual_created_at,
            'card_duplicate_ids'    => $cardDuplicateIds,
        ];
    }
}
