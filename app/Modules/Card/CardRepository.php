<?php

namespace App\Modules\Card;

use App\Models\Card;
use App\Models\CardDuplicate;
use App\Models\CardFavorite;
use App\Models\CardIntegration;
use App\Models\CardType;
use App\Models\CardView;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Exceptions\CardExists;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Exceptions\CardUserIdMustExist;
use App\Modules\Card\Exceptions\CardWorkspaceIdMustExist;
use App\Modules\Card\Exceptions\OauthKeyInvalid;
use App\Modules\Card\Helpers\ElasticQueryBuilderHelper;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use App\Modules\User\UserRepository;
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
    private UserRepository $userRepository;
    public const DEFAULT_SEARCH_LIMIT = 20;

    public function __construct(
        UserRepository $userRepository,
        OauthIntegrationRepository $oauthIntegration,
        ElasticQueryBuilderHelper $elasticQueryBuilderHelper,
        ThumbnailHelper $thumbnailHelper
    ) {
        $this->elasticQueryBuilderHelper = $elasticQueryBuilderHelper;
        $this->oauthIntegration = $oauthIntegration;
        $this->thumbnailHelper = $thumbnailHelper;
        $this->userRepository = $userRepository;
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
        // FILTER LIMIT
        // The limit for filter fields is 5 times the normal limit - so we can get tags/card types that aren't
        // in the current view for filtering. It can be defined with fl instead if preferred
        $limit = $constraints->get('l', self::DEFAULT_SEARCH_LIMIT);
        $filterLimit = $constraints->get('fl', $limit * 5);

        $rawQuery = Card::rawSearch()
            ->query($this->elasticQueryBuilderHelper->baseQuery($user, $constraints))
            ->collapse('card_duplicate_ids')
            ->source(['user_id', 'token', 'screenshot_thumbnail', 'screenshot_thumbnail_width', 'screenshot_thumbnail_height', 'thumbnail', 'thumbnail_width', 'thumbnail_height', 'description', 'type', 'type_tag', 'url', 'tags', 'title', 'content', 'favorites_by_user_id', 'created_at'])
            ->sortRaw($this->elasticQueryBuilderHelper->sortRaw($constraints))
            ->from($page * $limit)
            ->size($filterLimit);

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
     * This will sort an array that looks like this: ['Games', => 3] FIRST by the value
     * then alphabetically by the key. This is how we sort filters. This function doesn't
     * belong here I know. Too much work to put it somewhere better.
     */
    private function sortValueAndKey(array $array): array
    {
        $keys = array_keys($array);
        $values = array_values($array);
        array_multisort($values, SORT_DESC, $keys, SORT_ASC);

        return array_combine($keys, $values);
    }

    public function buildFilterResponse(Collection $hits): array
    {
        $tags = [];
        $cardTypes = [];
        $hits->each(static function ($hit) use (&$cardTypes, &$tags) {
            if (! empty($hit['_source']['type_tag'])) {
                $totalCardTypes = 1;
                if (! empty($cardTypes[$hit['_source']['type_tag']])) {
                    $totalCardTypes = (int) $cardTypes[$hit['_source']['type_tag']] + 1;
                }
                $cardTypes[$hit['_source']['type_tag']] = $totalCardTypes;
            }
            if (! empty($hit['_source']['tags'])) {
                foreach ($hit['_source']['tags'] as $tag) {
                    $totalTags = 1;
                    if (! empty($tags[$tag])) {
                        $totalTags = (int) $tags[$tag] + 1;
                    }
                    $tags[$tag] = $totalTags;
                }
            }
        });

        $tags = $this->sortValueAndKey($tags);
        $newTags = [];
        foreach ($tags as $tag => $count) {
            $newTags[] = ['name' => $tag, 'count' => $count];
        }

        $cardTypes = $this->sortValueAndKey($cardTypes);
        $newCardTypes = [];
        foreach ($cardTypes as $type => $count) {
            $newCardTypes[] = ['name' => $type, 'count' => $count];
        }

        return [
            'tags'  => $newTags,
            'types' => $newCardTypes,
        ];
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
        $hits = $result['hits']['hits'];
        $filters = $this->buildFilterResponse(collect($hits));
        // We'll often get more results than we need from elastic search for filters, so we'll slice the array here.
        $hits = collect(array_slice($hits, 0, $constraints->get('l', self::DEFAULT_SEARCH_LIMIT)));
        $totalResults = $result['hits']['total']['value'];

        $userIds = $hits->reduce(static function ($carry, $hit) {
            $userId = (int) $hit['_source']['user_id'];
            if (in_array($userId, $carry, true)) {
                return $carry;
            }
            $carry[] = $userId;

            return $carry;
        }, []);

        $users = User::whereIn('users.id', $userIds)->select('id', 'first_name', 'last_name', 'email')->get();

        $results = $hits->map(static function ($hit) use ($users, $user, $constraints) {
            $cardUser = $users->first(static function ($user) use ($hit) {
                return $hit['_source']['user_id'] === $user->id;
            });
            $fields = [];
            if (! $cardUser) {
                $fields['user']['id'] = null;
                $fields['user']['email'] = null;
                $fields['user']['first_name'] = null;
                $fields['user']['last_name'] = null;
                unset($hit['_source']['content']);
                Log::notice('CardUser: '.json_encode($cardUser).', Users: '.json_encode($users).', Hit: '.json_encode($hit));
            } else {
                $fields['user']['id'] = $cardUser->id;
                $fields['user']['email'] = $cardUser->email;
                $fields['user']['first_name'] = $cardUser->first_name;
                $fields['user']['last_name'] = $cardUser->last_name;
            }

            $fields['is_favorited'] = in_array($user->id, $hit['_source']['favorites_by_user_id'], true);
            $fields['total_favorites'] = count($hit['_source']['favorites_by_user_id']);
            $fields['id'] = (int) $hit['_id'];
            $fields['tags'] = $hit['_source']['tags'];
            $fields['url'] = $hit['_source']['url'];
            $fields['image'] = [
                'path'   => $hit['_source']['thumbnail'],
                'width'  => $hit['_source']['thumbnail_width'],
                'height' => $hit['_source']['thumbnail_height'],
            ];
            $fields['screenshot'] = [
                'path'   => $hit['_source']['screenshot_thumbnail'],
                'width'  => $hit['_source']['screenshot_thumbnail_width'],
                'height' => $hit['_source']['screenshot_thumbnail_height'],
            ];
            $fields['token'] = $hit['_source']['token'];
            $fields['description'] = $hit['_source']['description'];
            $fields['title'] = $hit['_source']['title'];
            $fields['type'] = $hit['_source']['type'];
            $fields['type_tag'] = $hit['_source']['type_tag'];
            $fields['created_at'] = Carbon::parse($hit['_source']['created_at'])->toIso8601String();
            if (! empty($hit['highlight']) && array_key_exists('h', $constraints->toArray())) {
                $fields['highlights'] = [];
                if (! empty($hit['highlight']['title'])) {
                    $fields['highlights']['title'] = $hit['highlight']['title'][0];
                }
                if (! empty($hit['highlight']['content'])) {
                    $fields['highlights']['content'] = $hit['highlight']['content'][0];
                }
            }

            return $fields;
        });

        return collect([
            // We may have an empty card if the card exists in elastic search, but not mysql
            'cards' => $results,
            'meta'  => [
                'page'          => (int) $constraints->get('p'),
                'total_pages'   => (int) ceil($totalResults / $constraints->get('l', self::DEFAULT_SEARCH_LIMIT)),
                'total_results' => $totalResults,
            ],
            'filters' => $filters,
        ]);
    }

    public function mapToFields(Card $card): array
    {
        $fields = collect($card->toArray());
        $newFields = [];
        foreach ($fields as $field => $fieldValue) {
            if ('actual_created_at' === $field) {
                if ($card->actual_created_at) {
                    $newFields['created_at'] = $card->actual_created_at->toIso8601String();
                }
                continue;
            }

            if ('total_favorites' === $field) {
                $newFields['total_favorites'] = (int) $fieldValue;
            }
            // Fields to remove
            if ('created_at' === $field || 'updated_at' === $field || 'properties' === $field || 'card_sync' === $field || 'card_type' === $field) {
                continue;
            }
            $newFields[$field] = $fieldValue;
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

    public function getWorkspace(Card $card): Workspace
    {
        return $card->workspace()->first();
    }

    public function getDuplicates(Card $card): Collection
    {
        $response = Http::post(Config::get('app.data_processing_url').'/dedupe', [
            'id'              => $card->id,
            'content'         => $card->content,
            'workspace_id'    => $card->workspace_id,
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
    public function getByForeignId(?string $foreignId, string $integrationKey): ?Card
    {
        if (! $foreignId) {
            return null;
        }
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
    public function saveView(array $fields, Card $card): void
    {
        if (! isset($fields['viewed_by'])) {
            return;
        }
        CardView::create([
            'card_id' => $card->id,
            'user_id' => $fields['viewed_by'],
        ]);
        ++$card->total_views;

        $card->save();
    }

    /**
     * @throws Exception
     */
    private function saveFavorited(array $fields, Card $card): void
    {
        if (! isset($fields['favorited_by']) && ! isset($fields['unfavorited_by'])) {
            return;
        }
        if (isset($fields['favorited_by'])) {
            CardFavorite::create([
                'card_id' => $card->id,
                'user_id' => $fields['favorited_by'],
            ]);
            ++$card->total_favorites;
        }
        if (isset($fields['unfavorited_by'])) {
            $cardFavorite = CardFavorite::where('card_id', $card->id)
                ->where('user_id', $fields['unfavorited_by'])
                ->first();
            if ($cardFavorite) {
                $cardFavorite->delete();

                --$card->total_favorites;
            }
        }
        $card->save();
    }

    public function getExistingCardId(string $url, int $userId, ?int $cardId = null): ?int
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

        $card = $query->first();

        return $card->id ?? null;
    }

    public function htmlDecodeFields(array $fields): array
    {
        foreach ($fields as $fieldKey => $field) {
            if ($field) {
                $fields[$fieldKey] = htmlspecialchars_decode($field);
            }
        }

        return $fields;
    }

    /**
     * @throws CardExists
     * @throws CardWorkspaceIdMustExist
     * @throws CardUserIdMustExist
     * @throws Exception
     */
    public function upsert(array $fields, ?Card $card = null, ?bool $getContentFromScreenshot = false): ?Card
    {
        $fields = $this->htmlDecodeFields($fields);
        $newFields = collect($fields);
        if ($card) {
            // If the url already exists on a different card let's not let them make an update
            if ($newFields->get('url') && (bool) $this->getExistingCardId($newFields->get('url'), (int) $card->user_id, $card->id)) {
                throw new CardExists('This user already has a card with this url. The update was unsuccessful.');
            }
            $card->update($fields);

            $this->thumbnailHelper->saveThumbnails($newFields, $card, $getContentFromScreenshot);
            $this->saveFavorited($fields, $card);
            $this->saveView($fields, $card);

            return $card;
        }

        if (! $newFields->get('user_id')) {
            throw new CardUserIdMustExist('You must include the user_id field when creating a new card.');
        }

        if (! $newFields->get('workspace_id')) {
            $workspaces = $this->userRepository->getAllWorkspaces(User::find($newFields->get('user_id')));
            if (1 === $workspaces->count()) {
                $newFields->put('workspace_id', $workspaces->get(0)->id);
            } else {
                throw new CardWorkspaceIdMustExist('This user belongs to more than one workspace. You must include the workspace_id field.');
            }
        }

        $existingCardId = $this->getExistingCardId($newFields->get('url'), $newFields->get('user_id'));
        // If a card with this url already exists, then just update the card instead
        if ($existingCardId && $newFields->get('url')) {
            return $this->upsert($fields, Card::find($existingCardId));
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

        $this->thumbnailHelper->saveThumbnails($newFields, $card, $getContentFromScreenshot);
        $this->saveFavorited($fields, $card);
        $this->saveView($fields, $card);

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
