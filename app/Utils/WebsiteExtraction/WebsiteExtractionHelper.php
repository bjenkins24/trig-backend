<?php

namespace App\Utils\WebsiteExtraction;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException;
use andreskrey\Readability\Readability;
use App\Utils\ExtractDataHelper;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use Campo\UserAgent;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Nesk\Puphpeteer\Puppeteer;

class WebsiteExtractionHelper
{
    private ExtractDataHelper $extractDataHelper;
    private WebsiteFactory $websiteFactory;

    public function __construct(
        ExtractDataHelper $extractDataHelper,
        WebsiteFactory $websiteFactory
    ) {
        $this->extractDataHelper = $extractDataHelper;
        $this->websiteFactory = $websiteFactory;
    }

    private function getHeaders(): array
    {
        $headers = [
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en,en-US;q=0,5',
        ];
        try {
            $headers['User-Agent'] = UserAgent::random();
        } catch (Exception $e) {
            // No-op
            // If for some reason we can't get a user agent, it's ok to just continue
        }

        return $headers;
    }

    /**
     * @throws WebsiteNotFound
     * @throws Exception       - We're going to catch this exception later and retry
     */
    public function simpleFetch(string $url): Website
    {
        $result = Http::withOptions([
            'referer' => true,
            'headers' => $this->getHeaders(),
        ])->get($url);

        if ($result->status() >= 400) {
            throw new WebsiteNotFound("The url returned an error code of {$result->status()} for $url");
        }

        return $this->websiteFactory->make($result->body());
    }

    /**
     * @throws WebsiteNotFound
     * @throws Exception       - We're going to catch this exception later and retry
     */
    public function fullFetch(string $url): Website
    {
        $puppeteer = new Puppeteer();
        $browser = $puppeteer->launch([
            'headless'        => true,
            'args'            => ['--no-sandbox', '--start-maximized', '--disable-dev-shm-usage'],
            'defaultViewport' => null,
        ]);

        $page = $browser->newPage();
        $page->setExtraHTTPHeaders($this->getHeaders());
        $response = $page->goto($url, [
            'timeout'   => 15,
        ]);
        $content = $page->content();
        $page->setViewport([
            'width'              => 1440,
            'height'             => 900,
            'deviceScaleFactor'  => 2,
        ]);
        $imagePath = Str::random().'.png';
        $page->screenshot(['path' => $imagePath, 'fullPage' => true]);

        $browser->close();

        if (isset($response->headers()['status'])) {
            $status = (int) $response->headers()['status'];
            if ($status >= 400) {
                throw new WebsiteNotFound("The url returned an error code of {$status} for $url");
            }
        }

        return $this->websiteFactory->make($content)->setScreenshot($imagePath);
    }

    /**
     * @throws JsonException
     */
    public function downloadAndExtract(string $url): Website
    {
        $result = collect($this->extractDataHelper->getData($url));

        return $this->websiteFactory->make($result->get('content'))
            ->setTitle($result->get('title'))
            ->setContent($result->get('content'))
            ->setExcerpt($result->get('excerpt'))
            ->setAuthor($result->get('author'));
    }

    /**
     * @throws ParseException
     */
    public function parseHtml(string $html): Collection
    {
        $readability = new Readability(new ReadabilityConfiguration());
        $readability->parse($html);

        $parsedHtml = $readability->getContent();
        if ($parsedHtml) {
            $parsedHtml = Str::purifyHtml($parsedHtml);
        }

        return collect([
            'image'   => $readability->getImage(),
            'author'  => $readability->getAuthor(),
            'excerpt' => $readability->getExcerpt(),
            'title'   => $readability->getTitle(),
            'html'    => $parsedHtml,
        ]);
    }
}
