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
            \Log::notice('6. Got website: '.json_encode($website));
        }
        if (2 === $currentRetryAttempt) {
            $website = $this->websiteExtractionHelper->simpleFetch($this->url);
            \Log::notice('7. Got website simple fetch: '.json_encode($website));
        }
        if (3 === $currentRetryAttempt) {
            $download = $this->websiteExtractionHelper->downloadAndExtract($this->url);
            \Log::notice('8. Got website download extract: '.json_encode($download));

            return $download;
        }

        try {
            $content = $website->parseContent();
            \Log::notice('9. Got website parse content:'.json_encode($content));

            return $content;
        } catch (ReadabilityParseException $e) {
            if (! $this->url) {
                \Log::notice('9.5. no url!:'.json_encode($website));

                return $website;
            }
            // If readability didn't work, let's try to download the file
            // and then parse it with Tika. Maybe we'll have better luck
            // This could be when full or simple fetch did NOT fail, but readability
            // didn't have enough context for whatever reason to parse the string
            $download = $this->websiteExtractionHelper->downloadAndExtract($this->url);
            \Log::notice('10. download extract:'.json_encode($download));

            return $download;
        }
    }
}
