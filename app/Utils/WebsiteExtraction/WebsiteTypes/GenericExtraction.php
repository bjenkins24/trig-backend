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
    public function getWebsite(): Collection
    {
        $html = $this->websiteExtractionHelper->fullFetch($this->url);

        return $this->websiteExtractionHelper->parseHtml($html);
    }
}
