<?php

namespace App\Utils\WebsiteExtraction;

use Illuminate\Support\Collection;

interface WebsiteExtractionInterface
{
    public function getWebsite(): Collection;
}
