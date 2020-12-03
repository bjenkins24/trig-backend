<?php

namespace App\Utils\WebsiteExtraction;

use Illuminate\Support\Collection;

interface WebsiteExtractionInterface
{
    public function getWebsite(int $currentRetryAttempt): Collection;
}
