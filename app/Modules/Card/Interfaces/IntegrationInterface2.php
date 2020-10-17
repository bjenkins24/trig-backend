<?php

namespace App\Modules\Card\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface IntegrationInterface2
{
    public function getAllCardData(User $user, ?int $since): Collection;
}
