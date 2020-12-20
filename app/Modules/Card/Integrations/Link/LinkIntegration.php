<?php

namespace App\Modules\Card\Integrations\Link;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Interfaces\IntegrationInterface;

class LinkIntegration implements IntegrationInterface
{
    public static function getIntegrationKey(): string
    {
        return 'link';
    }

    public function getAllCardData(User $user, Workspace $workspace, ?int $since): array
    {
        return [];
    }
}
