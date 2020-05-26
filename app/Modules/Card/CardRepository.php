<?php

namespace App\Modules\Card;

use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\User;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Helpers\ElasticQueryBuilderHelper;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use Illuminate\Support\Collection;

class CardRepository
{
    private OauthIntegrationRepository $oauthIntegration;
    private ElasticQueryBuilderHelper $elasticQueryBuilderHelper;

    public function __construct(
        OauthIntegrationRepository $oauthIntegration,
        ElasticQueryBuilderHelper $elasticQueryBuilderHelper
    ) {
        $this->elasticQueryBuilderHelper = $elasticQueryBuilderHelper;
        $this->oauthIntegration = $oauthIntegration;
    }

    /**
     * Find a connection for a user.
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

    public function searchCardsRaw(User $user, ?string $queryConstraints = null)
    {
        $searchLimit = 30;
        parse_str($queryConstraints, $queryConstraints);
        $constraints = collect($queryConstraints);

        $page = $constraints->get('page', 0);

        return Card::rawSearch()
            ->query($this->elasticQueryBuilderHelper->baseQuery($user, $constraints))
            ->from($page * $searchLimit)
            ->size($searchLimit)
            ->raw();
    }

    /**
     * Take the raw result from elastic search and fetch all info from the db.
     *
     * @return void
     */
    public function searchCards(User $user, ?string $queryConstraints = null)
    {
        $result = $this->searchCardsRaw($user, $queryConstraints);
        $hits = collect($result['hits']['hits']);
        $ids = $hits->map(function ($hit) {
            return $hit['_id'];
        });

        return Card::whereIn('id', $ids)
            ->select('id', 'user_id', 'title', 'card_type_id', 'image', 'actual_created_at', 'url')
            ->with(['user:id,first_name,last_name,email'])
            ->orderBy('actual_created_at', 'desc')
            ->get();
    }
}
