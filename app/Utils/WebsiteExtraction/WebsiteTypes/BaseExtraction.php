<?php

namespace App\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use App\Utils\WebsiteExtraction\WebsiteFactory;

abstract class BaseExtraction
{
    protected string $url;
    protected WebsiteExtractionHelper $websiteExtractionHelper;
    protected WebsiteFactory $websiteFactory;

    public function __construct(
        WebsiteExtractionHelper $websiteExtractionHelper,
        WebsiteFactory $websiteFactory
    ) {
        $this->websiteFactory = $websiteFactory;
        $this->websiteExtractionHelper = $websiteExtractionHelper;
    }

    public function setUrl(string $url): BaseExtraction
    {
        $this->url = $url;

        return $this;
    }
}
