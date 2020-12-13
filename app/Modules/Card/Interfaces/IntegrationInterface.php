<?php

namespace App\Modules\Card\Interfaces;

use App\Models\User;

interface IntegrationInterface
{
    public static function getIntegrationKey(): string;

    public function getAllCardData(User $user, int $organizationId, ?int $since): array;
}
