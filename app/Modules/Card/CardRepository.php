<?php

namespace App\Modules\Card;

use App\Models\Card;
use App\Models\CardDuplicate;
use App\Models\CardFavorite;
use App\Models\CardIntegration;
use App\Models\CardType;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Exceptions\CardExists;
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
use Illuminate\Support\Str;

class CardRepository
{
    private OauthIntegrationRepository $oauthIntegration;
    private ElasticQueryBuilderHelper $elasticQueryBuilderHelper;
    private ThumbnailHelper $thumbnailHelper;
    public const DEFAULT_SEARCH_LIMIT = 20;

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
        $limit = $constraints->get('l', self::DEFAULT_SEARCH_LIMIT);

        $rawQuery = Card::rawSearch()
            ->query($this->elasticQueryBuilderHelper->baseQuery($user, $constraints))
            ->collapse('card_duplicate_ids')
            ->from($page * $limit)
            ->size($limit);

        if ($constraints->get('h', 1) && $constraints->get('q')) {
            $rawQuery->highlightRaw([
                    'fields' => [
                        'title' => [
                            'order'               => 'score',
                            'number_of_fragments' => 1,
                        ],
                        'content' => [
                            'order'               => 'score',
                            'fragment_size'       => 500,
                            'number_of_fragments' => 1,
                        ],
                    ],
                    'pre_tags'  => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ]);
        }

        return $rawQuery->raw();
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
        $totalResults = $result['hits']['total']['value'];
        $ids = $hits->map(static function ($hit) {
            return $hit['_id'];
        });

        $query = Card::whereIn('cards.id', $ids)
            ->select('id', 'token', 'user_id', 'title', 'card_type_id', 'image', 'image_width', 'image_height', 'actual_created_at', 'url', 'total_favorites', 'description')
            ->with('user:id,first_name,last_name,email')
            ->with('cardType:id,name')
            ->with('cardFavorite:card_id')
            ->with('cardSync:card_id,created_at');

        if (! $constraints->get('q')) {
            $query->orderBy('actual_created_at', 'desc');
        }

        $result = $query->get();

        if ($constraints->get('q')) {
            $result = $hits->map(static function ($hit) use ($result) {
                // Sort by score
                $final = $result->first(static function ($card) use ($hit) {
                    return $card->id === (int) $hit['_id'];
                });
                // Add id of elastic search hit that _isn't_ in mysql
                if (! $final) {
                    return collect(['id' => $hit['_id']]);
                }

                return $final;
            });
        }

        $result = collect($result->map(function ($card) use ($hits, $constraints) {
            // If the card exists in elastic search, but not in the database we need to set up a guard
            if (! $card->get('title')) {
                Log::notice('A card exists in elastic search, but not within the database. Card id: '.$card->get('id'));
                // TODO: It's probably safe to remove the card from elastic search here
                return [];
            }
            $lastAttemptedSync = null;
            if ($card->cardSync) {
                $lastAttemptedSync = $card->cardSync->created_at->toIso8601String();
            }
            $isFavorited = false;
            if ($card->cardFavorite) {
                $isFavorited = (bool) $card->cardFavorite->card_id;
            }
            $user = null;
            if ($card->user) {
                $user['id'] = $card->user['id'];
                $user['firstName'] = $card->user['first_name'];
                $user['lastName'] = $card->user['last_name'];
                $user['email'] = $card->user['email'];
            }
            $fields = $this->mapToFields($card);
            $fields['isFavorited'] = $isFavorited;
            $fields['lastAttemptedSync'] = $lastAttemptedSync;
            $fields['user'] = $user;
            if (array_key_exists('h', $constraints->toArray())) {
                $hit = $hits->first(static function ($hit) use ($card) {
                    return (int) $hit['_id'] === $card->id;
                });
                if (! empty($hit['highlight'])) {
                    $fields['highlights'] = [];
                    if (! empty($hit['highlight']['title'])) {
                        $fields['highlights']['title'] = $hit['highlight']['title'][0];
                    }
                    if (! empty($hit['highlight']['content'])) {
                        $fields['highlights']['content'] = $hit['highlight']['content'][0];
                    }
                }
            }

            return $fields;
        }));

        return collect([
            // We may have an empty card if the card exists in elastic search, but not mysql
            'cards' => $result->filter(static function ($card) {
                return ! empty($card);
            }),
            'meta' => [
                'page'         => (int) $constraints->get('p'),
                'totalPages'   => (int) ceil($totalResults / $constraints->get('l', self::DEFAULT_SEARCH_LIMIT)),
                'totalResults' => $totalResults,
            ],
        ]);
    }

    public function mapToFields(Card $card): array
    {
        $fields = collect($card->toArray());
        $newFields = [];
        foreach ($fields as $field => $fieldValue) {
            if ('actual_created_at' === $field) {
                $newFields['createdAt'] = $card->actual_created_at->toIso8601String();
                continue;
            }
            if ('actual_updated_at' === $field) {
                $newFields['updatedAt'] = $card->actual_updated_at->toIso8601String();
                continue;
            }
            if ('card_type_id' === $field) {
                $cardType = null;
                if ($card->cardType) {
                    $cardType = $card->cardType->name;
                }
                $newFields['cardType'] = $cardType;
                unset($cardTypeId);
            }
            if ('total_favorites' === $field) {
                $newFields['totalFavorites'] = (int) $fieldValue;
            }
            // Fields to remove
            if ('created_at' === $field || 'updated_at' === $field || 'properties' === $field || 'card_sync' === $field || 'card_type' === $field) {
                continue;
            }
            $newFields[Str::camel($field)] = $fieldValue;
        }

        return $newFields;
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
            if ($carry && $card->actual_updated_at->isBefore($carry['modified'])) {
                return $carry;
            }
            if (! $carry || ($carry && $card->actual_updated_at->isAfter($carry['modified']))) {
                return ['id' => $id, 'modified' => $card->actual_updated_at];
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

    public function cardExists(string $url, int $userId, ?int $cardId = null): bool
    {
        // Get common starting url - https and www
        if (! Str::contains($url, '://')) {
            $url = 'https://'.$url;
        }
        if (! Str::contains($url, '://www.')) {
            $url = Str::replaceFirst('://', '://www.', $url);
        }
        $url = Str::replaceFirst('http://', 'https://', $url);

        $query = Card::where('user_id', $userId)
            ->where(static function ($query) use ($url) {
                // https://www.mycooltest.com
                $query->where('url', $url)
                    // https://mycooltest.com
                    ->orWhere('url', Str::replaceFirst('www.', '', $url))
                    // http://www.mycooltest.com
                    ->orWhere('url', Str::replaceFirst('https://', 'http://', $url))
                    // http://mycooltest.com
                    ->orWhere('url', Str::replaceFirst('www.', '', Str::replaceFirst('https://', 'http://', $url)))
                    // mycooltest.com
                    ->orWhere('url', Str::replaceFirst('https://www.', '', $url))
                    // www.mycooltest.com
                    ->orWhere('url', Str::replaceFirst('https://', '', $url));
            });

        if ($cardId) {
            $query->where('id', '!=', $cardId);
        }

        return $query->exists();
    }

    /**
     * @throws Exception
     */
    public function updateOrInsert(array $fields, ?Card $card = null): ?Card
    {
        $newFields = collect($fields);
        if ($card) {
            // If the url already exists on a different card let's not let them make an update
            if ($newFields->get('url') && $this->cardExists($newFields->get('url'), (int) $card->user_id, $card->id)) {
                throw new CardExists('This user already has a card with this url. The update was unsuccessful.');
            }
            $card->update($fields);
            if ($newFields->get('image')) {
                $this->thumbnailHelper->saveThumbnail($newFields->get('image'), $card);
            }
            $this->saveFavorited($fields, $card);

            return $card;
        }

        if (! $newFields->get('user_id')) {
            throw new Exception('You must supply a user_id');
        }
        if (! $newFields->get('card_type_id')) {
            throw new Exception('You must supply a card_type_id');
        }

        if ($newFields->get('url') && $this->cardExists($newFields->get('url'), $newFields->get('user_id'))) {
            throw new CardExists('This user already has a card with this url. The card was not created.');
        }

        if (! $newFields->get('actual_created_at')) {
            $newFields->put('actual_created_at', Carbon::now());
        }
        if (! $newFields->get('actual_updated_at')) {
            $newFields->put('actual_updated_at', Carbon::now());
        }

        // Remove text matching if a user included it in the url so search will work correctly
        if (Str::contains($newFields->get('url'), '#:~:text')) {
            $newFields->put('url', substr($newFields->get('url'), 0, strpos($newFields->get('url'), '#:~:text')));
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
