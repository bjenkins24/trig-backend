<?php

namespace App\Modules\Card\Interfaces;

use App\Models\User;

interface IntegrationInterface
{
    public static function getKey(): string;

    public function syncCards(User $user);
}
