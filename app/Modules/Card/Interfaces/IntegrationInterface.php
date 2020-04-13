<?php

namespace App\Modules\Card\Interfaces;

interface IntegrationInterface
{
    public function syncCards($user);
}
