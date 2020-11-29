<?php

namespace App\Utils\WebsiteExtraction;

use App\Utils\WebsiteExtraction\WebsiteTypes\GenericExtraction;
use App\Utils\WebsiteExtraction\WebsiteTypes\GoogleDocsExtraction;
use Illuminate\Support\Str;

class WebsiteExtractionFactory
{
    public function make(string $url)
    {
        $websiteExtraction = null;
        if (Str::contains($url, 'docs.google.com')) {
            $websiteExtraction = app(GoogleDocsExtraction::class);
        }

        if (! $websiteExtraction) {
            $websiteExtraction = app(GenericExtraction::class);
        }

        $websiteExtraction->setUrl($url);

        return $websiteExtraction;
    }
}
