<?php

namespace App\Modules\Card\Interfaces;

use App\Models\Organization;
use App\Models\User;

interface IntegrationInterface
{
    public static function getIntegrationKey(): string;

    public function getAllCardData(User $user, Organization $organization, ?int $since): array;
}
