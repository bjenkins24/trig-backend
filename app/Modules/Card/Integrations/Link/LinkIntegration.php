<?php

namespace App\Modules\Card\Integrations\Link;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Interfaces\IntegrationInterface;

class LinkIntegration implements IntegrationInterface
{
    public static function getIntegrationKey(): string
    {
        return 'link';
    }

    public function getAllCardData(User $user, Organization $organization, ?int $since): array
    {
        return [];
    }
}
