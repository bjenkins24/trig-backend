<?php

namespace App\Modules\Card\Integrations;

use App\Modules\Card\Interfaces\IntegrationInterface;

class Google extends BaseIntegration implements IntegrationInterface
{
    public function syncCards()
    {
        return $this->user;
    }
}
