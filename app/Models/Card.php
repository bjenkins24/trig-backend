<?php

namespace App\Models;

use App\Modules\Card\CardRepository;
use App\Support\Traits\Relationships\BelongsToCardType;
use App\Support\Traits\Relationships\BelongsToUser;
use App\Support\Traits\Relationships\HasCardData;
use App\Support\Traits\Relationships\HasCardFavorite;
use App\Support\Traits\Relationships\HasCardIntegration;
use App\Support\Traits\Relationships\LinkShareable;
use App\Support\Traits\Relationships\Permissionables;
use ElasticScoutDriverPlus\CustomSearch;
use Laravel\Scout\Searchable;

class Card extends BaseModel
{
    use BelongsToUser;
    use BelongsToCardType;
    use HasCardFavorite;
    use HasCardIntegration;
    use HasCardData;
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
    ];

    /**
     * The fields index by elastic search.
     *
     * @return void
     */
    public function toSearchableArray()
    {
        $cardRepo = app(CardRepository::class);
        $permissions = $cardRepo->denormalizePermissions($this)->toArray();
        $cardData = $cardRepo->getCardDataForIndex($this);

        return array_merge([
            'user_id'           => $this->user_id,
            'card_type_id'      => $this->card_type_id,
            'organization_id'   => $this->user()->first()->organizations()->first()->id,
            'title'             => $this->title,
            'permissions'       => $permissions,
            'actual_created_at' => $this->actual_created_at,
        ], $cardData);
    }
}
