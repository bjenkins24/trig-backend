<?php

namespace App\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionInterface;
use Exception;
use Illuminate\Support\Collection;

class GenericExtraction extends BaseExtraction implements WebsiteExtractionInterface
{
    /**
     * @throws Exception
     */
    public function getWebsite(int $currentRetryAttempt): Collection
    {
        if ($currentRetryAttempt < 2) {
            $html = $this->websiteExtractionHelper->fullFetch($this->url);
        } else {
            // We're going to try a simple fetch if the full fetch failed twice
            // A simple fetch will also get the application/type of a url in the case
            // that it's a file like a pdf - which we can then download and send to tika
            $html = $this->websiteExtractionHelper->simpleFetch($this->url);
        }

        return $this->websiteExtractionHelper->parseHtml($html);
    }
}
