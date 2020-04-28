<?php

namespace App\Modules\Card;

use App\Models\Card;
use App\Models\CardIntegration;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\OauthIntegration\OauthIntegrationRepository;

class CardRepository
{
    public OauthIntegrationRepository $oauthIntegration;

    public function __construct(OauthIntegrationRepository $oauthIntegration)
    {
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
}
