<?php

namespace Tests\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\Website;
use App\Utils\WebsiteExtraction\WebsiteFactory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;

trait MockWebsiteTrait
{
    /**
     * @throws BindingResolutionException
     */
    private function getMockWebsite(string $rawHtml): Website
    {
        return app(WebsiteFactory::class)
            ->make($rawHtml)
            ->setImage('/public/image')
            ->setAuthor('my author')
            ->setExcerpt('my excerpt')
            ->setTitle('my title');
    }

    private function getMockParseHtml($website): Collection
    {
        return collect([
            'image'   => $website->getImage(),
            'author'  => $website->getAuthor(),
            'excerpt' => $website->getExcerpt(),
            'title'   => $website->getTitle(),
            'html'    => $website->getRawContent(),
        ]);
    }
}
