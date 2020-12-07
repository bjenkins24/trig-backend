<?php

namespace App\Utils\WebsiteExtraction;

interface WebsiteExtractionInterface
{
    public function getWebsite(int $currentRetryAttempt): Website;
}
