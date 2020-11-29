<?php

namespace App\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;

abstract class BaseExtraction
{
    protected string $url;
    protected WebsiteExtractionHelper $websiteExtractionHelper;

    public function __construct(
        WebsiteExtractionHelper $websiteExtractionHelper
    ) {
        $this->websiteExtractionHelper = $websiteExtractionHelper;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
