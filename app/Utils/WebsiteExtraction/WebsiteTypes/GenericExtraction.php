<?php

namespace App\Utils\WebsiteExtraction\WebsiteTypes;

use andreskrey\Readability\ParseException as ReadabilityParseException;
use App\Utils\WebsiteExtraction\Website;
use App\Utils\WebsiteExtraction\WebsiteExtractionInterface;
use Exception;

class GenericExtraction extends BaseExtraction implements WebsiteExtractionInterface
{
    /**
     * @throws Exception
     */
    public function getWebsite(int $currentRetryAttempt = 0): Website
    {
        $website = $this->websiteFactory->make();
        // Full fetch will intermittently timeout. So let's try it twice.
        if ($currentRetryAttempt < 2) {
            $website = $this->websiteExtractionHelper->fullFetch($this->url);
        }
        if (2 === $currentRetryAttempt) {
            $website = $this->websiteExtractionHelper->simpleFetch($this->url);
        }
        if (3 === $currentRetryAttempt) {
            $download = $this->websiteExtractionHelper->downloadAndExtract($this->url);

            return $download;
        }

        try {
            $content = $website->parseContent();

            return $content;
        } catch (ReadabilityParseException $e) {
            if (! $this->url) {
                return $website;
            }
            // If readability didn't work, let's try to download the file
            // and then parse it with Tika. Maybe we'll have better luck
            // This could be when full or simple fetch did NOT fail, but readability
            // didn't have enough context for whatever reason to parse the string
            $download = $this->websiteExtractionHelper->downloadAndExtract($this->url);

            return $download;
        }
    }
}
