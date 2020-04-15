<?php

namespace App\Modules\Card\Interfaces;

interface IntegrationInterface
{
    public static function getKey(): string;

    public function syncCards($user);
}
