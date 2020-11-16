<?php

namespace App\Modules\Card;

use App\Models\Card;
use App\Models\CardDuplicate;
use App\Models\CardFavorite;
use App\Models\CardIntegration;
use App\Models\CardType;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Exceptions\OauthKeyInvalid;
use App\Modules\Card\Helpers\ElasticQueryBuilderHelper;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CardRepository
{
    private OauthIntegrationRepository $oauthIntegration;
    private ElasticQueryBuilderHelper $elasticQueryBuilderHelper;
    private ThumbnailHelper $thumbnailHelper;
    private const SEARCH_LIMIT = 90;

    public function __construct(
        OauthIntegrationRepository $oauthIntegration,
        ElasticQueryBuilderHelper $elasticQueryBuilderHelper,
        ThumbnailHelper $thumbnailHelper
    ) {
        $this->elasticQueryBuilderHelper = $elasticQueryBuilderHelper;
        $this->oauthIntegration = $oauthIntegration;
        $this->thumbnailHelper = $thumbnailHelper;
    }

    /**
     * Find a connection for a user.
     *
     * @param string|int $foreignId
     *
     * @throws CardIntegrationCreationValidate
     */
    public function createIntegration(Card $card, $foreignId, string $integrationName): ?CardIntegration
    {
        $oauthIntegration = $this->oauthIntegration->findByName($integrationName);
        if (! $oauthIntegration || ! $card->id) {
            throw new CardIntegrationCreationValidate('The integration name you passed in doesn\'t exist. The card integration was not
                created for card '.$card->id.' with the foreign id of '.$foreignId.' and the key of
                '.$integrationName);
        }

        return $card->cardIntegration()->create([
            'foreign_id'           => $foreignId,
            'oauth_integration_id' => $oauthIntegration->id,
        ]);
    }

    /**
     * Denormalize permissions into a single array.
     */
    public function denormalizePermissions(Card $card): Collection
    {
        return $card->permissions()->get()->map(function ($permission) {
            $permissionType = $permission->permissionType()->first();

            if (! $permissionType) {
                return [];
            }

            if (! $permissionType->typeable_type) {
                return [
                    'type' => null,
                    'id'   => null,
                ];
            }

            return [
                'type' => $permissionType->typeable_type,
                'id'   => $permissionType->typeable_id,
            ];
        });
    }

    public function searchCardsRaw(User $user, Collection $constraints): array
    {
        $page = $constraints->get('p', 0);

        return Card::rawSearch()
            ->query($this->elasticQueryBuilderHelper->baseQuery($user, $constraints))
            ->collapse('card_duplicate_ids')
            ->highlightRaw([
                'fields' => [
                    'title' => [
                        'number_of_fragments' => 1,
                    ],
                    'content' => [
                        'number_of_fragments' => 1,
                        'fragment_size'       => 200,
                    ],
                ],
            ])
            ->from($page * self::SEARCH_LIMIT)
            ->size(self::SEARCH_LIMIT)
            ->raw();
    }

    /**
     * Take the raw result from elastic search and fetch all info from the db.
     */
    public function searchCards(User $user, ?Collection $constraints = null): Collection
    {
        if (! $constraints) {
            $constraints = collect([]);
        }
        $result = $this->searchCardsRaw($user, $constraints);
        $hits = collect($result['hits']['hits']);
        $ids = $hits->map(static function ($hit) {
            return $hit['_id'];
        });

        $result = Card::whereIn('cards.id', $ids)
            ->select('id', 'token', 'user_id', 'title', 'card_type_id', 'image', 'image_width', 'image_height', 'actual_created_at', 'url', 'total_favorites')
            ->with('user:id,first_name,last_name,email')
            ->with('cardType:id,name')
            ->with('cardFavorite:card_id')
            ->with('cardSync:card_id,created_at')
            ->orderBy('actual_created_at', 'desc')
            ->get();

        return collect($result->map(static function ($card) {
            $lastAttemptedSync = null;
            if ($card->cardSync) {
                $lastAttemptedSync = $card->cardSync->created_at->toIso8601String();
            }
            $isFavorited = false;
            if ($card->cardFavorite) {
                $isFavorited = (bool) $card->cardFavorite->card_id;
            }
            $cardType = null;
            if ($card->cardType) {
                $cardType = $card->cardType->name;
            }
            $user = null;
            if ($card->user) {
                $user['id'] = $card->user['id'];
                $user['firstName'] = $card->user['first_name'];
                $user['lastName'] = $card->user['last_name'];
                $user['email'] = $card->user['email'];
            }
            $createdAt = $card->actual_created_at;
            if ($createdAt) {
                $createdAt = $createdAt->toIso8601String();
            }
            $modifiedAt = $card->actual_modified_at;
            if ($modifiedAt) {
                $modifiedAt = $modifiedAt->toIso8601String();
            }

            return [
                'id'                 => $card->id,
                'user'               => $user,
                'token'              => $card->token,
                'cardType'           => $cardType,
                'title'              => $card->title,
                'url'                => $card->url,
                'image'              => $card->image,
                'imageWidth'         => $card->image_width,
                'imageHeight'        => $card->image_height,
                'totalFavorites'     => (int) $card->total_favorites,
                'isFavorited'        => $isFavorited,
                'lastAttemptedSync'  => $lastAttemptedSync,
                'createdAt'          => $createdAt,
                'modifiedAt'         => $modifiedAt,
            ];
        }));
    }

    public function getCardIntegration(Card $card): ?CardIntegration
    {
        return $card->cardIntegration()->first();
    }

    public function getCardType(Card $card): ?CardType
    {
        return $card->cardType()->first();
    }

    public function getUser(Card $card): ?User
    {
        return $card->user()->first();
    }

    public function getOrganization(Card $card): Organization
    {
        return $card->user()->first()->organizations()->first();
    }

    public function getDuplicates(Card $card): Collection
    {
        $response = Http::post(Config::get('app.data_processing_url').'/dedupe', [
            'id'              => $card->id,
            'content'         => $card->content,
            'organization_id' => $this->getOrganization($card)->id,
            'key'             => Config::get('app.data_processing_api_key'),
        ]);
        $statusCode = $response->getStatusCode();
        if (200 !== $statusCode) {
            Log::notice('Deduping the card with the id '.$card->id.' from user '.$card->user()->first()->id.' failed with status code '.$statusCode);

            return collect([]);
        }

        return collect(json_decode((string) $response->getBody()));
    }

    public function dedupe(Card $card): bool
    {
        if (! $card->content) {
            return false;
        }
        $duplicateIds = $this->getDuplicates($card);
        if ($duplicateIds->isEmpty()) {
            return false;
        }
        $duplicateIds->push($card->id);
        // Get the most recently modified card duplicate and make that primary
        $primary = $duplicateIds->reduce(function ($carry, $id) {
            $card = Card::find($id);
            if ($carry && $card->actual_modified_at->isBefore($carry['modified'])) {
                return $carry;
            }
            if (! $carry || ($carry && $card->actual_modified_at->isAfter($carry['modified']))) {
                return ['id' => $id, 'modified' => $card->actual_modified_at];
            }
        });

        DB::transaction(function () use ($card, $duplicateIds, $primary) {
            $primaryDuplicates = $card->cardDuplicates()->get();
            $primaryDuplicates->each(function ($cardDuplicate) {
                $cardDuplicate->delete();
            });
            $primaryDuplicate = $card->primaryDuplicate()->first();
            if ($primaryDuplicate) {
                $primaryDuplicate->delete();
            }

            $duplicateIds->each(function ($id) use ($primary) {
                if ($id === $primary['id']) {
                    return;
                }
                CardDuplicate::create([
                    'primary_card_id'   => $primary['id'],
                    'duplicate_card_id' => $id,
                ]);
            });
            $card->searchable();
        });

        return true;
    }

    /**
     * Get duplicate ids in the form of a string for elastic search
     * we collapse the elastic search query based on this field to make
     * sure we only get one card that has duplicates. In order to make that happen
     * each card that is a duplicate must have an identical field which is what we're
     * making here.
     */
    public function getDuplicateIds(Card $card): string
    {
        $duplicates = $card->cardDuplicates()->get();
        if ($duplicates->isEmpty()) {
            return (string) $card->id;
        }
        $ids = $duplicates->reduce(function ($carry, $duplicate) {
            $primary = $duplicate->primary_card_id;
            $secondary = $duplicate->duplicate_card_id;
            if (! $carry->contains($primary)) {
                $carry->push($primary);
            }
            if (! $carry->contains($secondary)) {
                $carry->push($secondary);
            }

            return $carry;
        }, collect([]));

        $ids = $ids->sort()->values();

        return $ids->sort()->values()->reduce(function ($carry, $id) {
            if (! $carry) {
                return (string) $id;
            }

            return "{$carry}_{$id}";
        });
    }

    /**
     * @throws OauthKeyInvalid
     */
    public function getByForeignId(string $foreignId, string $integrationKey): ?Card
    {
        $oauthIntegration = $this->oauthIntegration->findByName($integrationKey);
        if (! $oauthIntegration) {
            throw new OauthKeyInvalid("The integration key $integrationKey does not exist");
        }
        $cardIntegration = CardIntegration::where([
            'foreign_id'           => $foreignId,
            'oauth_integration_id' => $oauthIntegration->id,
        ])->first();
        if (! $cardIntegration) {
            return null;
        }

        return $cardIntegration->card()->first();
    }

    /**
     * @throws Exception
     */
    private function saveFavorited(array $fields, Card $card): void
    {
        if (isset($fields['isFavorited']) && $fields['isFavorited']) {
            CardFavorite::create([
                'card_id' => $card->id,
                'user_id' => $card->user_id,
            ]);
            ++$card->total_favorites;
        }
        if (isset($fields['isFavorited']) && ! $fields['isFavorited']) {
            $cardFavorite = CardFavorite::where('card_id', $card->id)
                ->where('user_id', $card->user_id)
                ->first();
            if ($cardFavorite) {
                $cardFavorite->delete();

                --$card->total_favorites;
            }
        }
        $card->save();
    }

    /**
     * @throws Exception
     */
    public function updateOrInsert(array $fields, ?Card $card = null): ?Card
    {
        $newFields = collect($fields);
        if ($card) {
            $card->update($fields);
            if ($newFields->get('image')) {
                $this->thumbnailHelper->saveThumbnail($newFields->get('image'), $card);
            }
            $this->saveFavorited($fields, $card);

            return $card;
        }

        if (! $newFields->get('actual_created_at')) {
            $newFields->put('actual_created_at', Carbon::now());
        }
        if (! $newFields->get('actual_modified_at')) {
            $newFields->put('actual_modified_at', Carbon::now());
        }

        $newFields->put('token', bin2hex(random_bytes(24)));

        $card = Card::create($newFields->toArray());
        if ($newFields->get('image')) {
            $this->thumbnailHelper->saveThumbnail($newFields->get('image'), $card);
        }
        $this->saveFavorited($fields, $card);

        return $card;
    }

    public function removeAllPermissions(Card $card): void
    {
        $permissions = $card->permissions()->get();
        if (! $permissions) {
            return;
        }
        $permissions->each(static function ($permission) {
            $permission->delete();
        });
    }
}
