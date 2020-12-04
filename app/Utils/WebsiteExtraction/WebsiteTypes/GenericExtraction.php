<?php

namespace App\Utils\WebsiteExtraction\WebsiteTypes;

use andreskrey\Readability\ParseException as ReadabilityParseException;
use App\Utils\WebsiteExtraction\WebsiteExtractionInterface;
use Exception;
use Illuminate\Support\Collection;

class GenericExtraction extends BaseExtraction implements WebsiteExtractionInterface
{
    /**
     * @throws Exception
     */
    public function getWebsite(int $currentRetryAttempt = 0): Collection
    {
        $html = '';
        // Full fetch will intermittently timeout. So let's try it twice.
        if ($currentRetryAttempt < 2) {
            try {
                $html = $this->websiteExtractionHelper->fullFetch($this->url);
            } catch (Exception $exception) {
                // TODO: I DO NOT WANT TO CATCH HERE. I want Link Content to catch errors
                // and handle retry logic. But it's not catching any errors! ACK
                $html = '';
            }
        }
        if (3 === $currentRetryAttempt) {
            $html = $this->websiteExtractionHelper->simpleFetch($this->url);
        }
        if (4 === $currentRetryAttempt) {
            return $this->websiteExtractionHelper->downloadAndExtract($this->url);
        }

        try {
            return $this->websiteExtractionHelper->parseHtml($html);
        } catch (ReadabilityParseException $e) {
            if (! $this->url) {
                return collect([]);
            }
            // If readability didn't work, let's try to download the file
            // and then parse it with Tika. Maybe we'll have better luck
            // This could be when full or simple fetch did NOT fail, but readability
            // didn't have enough context for whatever reason to parse the string
            return $this->websiteExtractionHelper->downloadAndExtract($this->url);
        }
    }
}
