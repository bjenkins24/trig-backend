<?php

namespace App\Utils\WebsiteExtraction;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException;
use andreskrey\Readability\Readability;
use App\Utils\ExtractDataHelper;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use Campo\UserAgent;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use JsonException;
use Mews\Purifier\Facades\Purifier;
use Nesk\Puphpeteer\Puppeteer;

class WebsiteExtractionHelper
{
    private ExtractDataHelper $extractDataHelper;

    public function __construct(ExtractDataHelper $extractDataHelper)
    {
        $this->extractDataHelper = $extractDataHelper;
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
    public function simpleFetch(string $url): Response
    {
        $result = Http::withOptions([
            'referer' => true,
            'headers' => $this->getHeaders(),
        ])->get($url);

        if ($result->status() >= 400) {
            throw new WebsiteNotFound("The url returned an error code of {$result->status()} for $url");
        }

        return $result;
    }

    /**
     * @throws WebsiteNotFound
     * @throws Exception       - We're going to catch this exception later and retry
     */
    public function fullFetch(string $url): string
    {
        $puppeteer = new Puppeteer();
        $browser = $puppeteer->launch();

        $page = $browser->newPage();
        $page->setExtraHTTPHeaders($this->getHeaders());
        $response = $page->goto($url);
        $content = $page->content();

        $browser->close();

        if (isset($response->headers()['status'])) {
            $status = (int) $response->headers()['status'];
            if ($status >= 400) {
                throw new WebsiteNotFound("The url returned an error code of {$status} for $url");
            }
        }

        return $content;
    }

    /**
     * @throws JsonException
     */
    public function downloadAndExtract(string $url): Collection
    {
        $result = collect($this->extractDataHelper->getData($url));

        return collect([
            'image'   => null,
            'author'  => $result->get('author'),
            'excerpt' => $result->get('excerpt'),
            'title'   => $result->get('title'),
            'html'    => $result->get('content'),
        ]);
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
            $parsedHtml = Purifier::clean($parsedHtml);
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
