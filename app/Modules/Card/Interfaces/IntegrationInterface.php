<?php

namespace App\Modules\Card\Interfaces;

use App\Models\User;
use App\Models\Workspace;

interface IntegrationInterface
{
    public static function getIntegrationKey(): string;

    public function getAllCardData(User $user, Workspace $workspace, ?int $since): array;
}
