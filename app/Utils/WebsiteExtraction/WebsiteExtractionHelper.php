<?php

namespace App\Utils\WebsiteExtraction;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException as ReadabilityParseException;
use andreskrey\Readability\Readability;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use Campo\UserAgent;
use DOMDocument;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use League\HTMLToMarkdown\HtmlConverter;
use Mews\Purifier\Facades\Purifier;
use Nesk\Puphpeteer\Puppeteer;

class WebsiteExtractionHelper
{
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
     */
    public function fullFetch(string $url): string
    {
        $puppeteer = new Puppeteer();
        try {
            $browser = $puppeteer->launch();

            $page = $browser->newPage();
            $page->setExtraHTTPHeaders($this->getHeaders());
            $response = $page->goto($url);
            $content = $page->content();

            $browser->close();
        } catch (Exception $exception) {
            // Timed out
            $content = '';
        }

        if (isset($response, $response->headers()['status'])) {
            $status = (int) $response->headers()['status'];
            if ($status >= 400) {
                throw new WebsiteNotFound("The url returned an error code of {$status} for $url");
            }
        }

        return $content;
    }

    public function removeTag(string $html, string $tag): string
    {
        $doc = new DOMDocument();
        try {
            $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        } catch (Exception $error) {
            return $html;
        }
        $tags = $doc->getElementsByTagName($tag);
        $length = $tags->length;
        for ($i = 0; $i < $length; ++$i) {
            if ($tags->item($i)) {
                $tags->item($i)->parentNode->removeChild($tags->item($i));
            }
        }

        return $doc->saveHTML();
    }

    public function makeContentSearchable(?string $html): string
    {
        if (! $html) {
            return '';
        }
        // Header tags don't render too well in plain text
        // making the full size don't look good either so we're just removing them
        $tagsToRemove = [
            'figcaption',
            'figure',
            'script',
            'style',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
        ];

        foreach ($tagsToRemove as $tag) {
            $html = $this->removeTag($html, $tag);
        }

        // This will remove images altogether, but it will also remove anchors while preserving their text content
        $parsedHtml = strip_tags($html, '<p><em><strong><pre><b><i><ul><ol><li><table><tr><td><th><br><blockquote>');

        return (new HtmlConverter(['strip_tags' => true]))->convert($parsedHtml);
    }

    public function parseHtml(string $html): Collection
    {
        $readability = new Readability(new ReadabilityConfiguration());

        try {
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
        } catch (ReadabilityParseException $e) {
            echo sprintf('Error processing text: %s', $e->getMessage());

            return collect([]);
        }
    }
}
