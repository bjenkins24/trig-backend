<?php

namespace App\Modules\Card\Interfaces;

use App\Models\User;

interface IntegrationInterface
{
    public function syncCards(User $user);
}
