<?php

namespace App\Utils\WebsiteExtraction;

class WebsiteFactory
{
    private Website $website;

    public function __construct(Website $website)
    {
        $this->website = $website;
    }

    public function make(?string $content = ''): Website
    {
        return $this->website->setRawContent($content);
    }
}
