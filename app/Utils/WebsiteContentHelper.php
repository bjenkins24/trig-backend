<?php

namespace App\Utils;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException as ReadabilityParseException;
use andreskrey\Readability\Readability;
use Campo\UserAgent;
use DOMDocument;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Nesk\Puphpeteer\Puppeteer;

class WebsiteContentHelper
{
    private function getHeaders(): array
    {
        return [
            'User-Agent'      => UserAgent::random(),
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en,en-US;q=0,5',
       ];
    }

    /**
     * @throws Exception
     */
    public function simpleFetch(string $url): Response
    {
        return Http::withOptions([
            'referer' => true,
            'headers' => $this->getHeaders(),
        ])->get($url);
    }

    /**
     * @throws Exception
     */
    public function fullFetch(string $url): string
    {
        $puppeteer = new Puppeteer();
        $browser = $puppeteer->launch();

        $page = $browser->newPage();
        $page->setExtraHTTPHeaders($this->getHeaders());
        $page->goto($url);
        $content = $page->content();

        $browser->close();

        return $content;
    }

    /**
     * @throws Exception
     */
    private function fetchWebsite(string $url): string
    {
        if (Str::contains($url, 'docs.google.com')) {
            return $this->simpleFetch($url);
        }

        return $this->fullFetch($url);
    }

    public function removeTag(string $html, string $tag): string
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $tags = $doc->getElementsByTagName($tag);
        $length = $tags->length;
        for ($i = 0; $i < $length; ++$i) {
            $tags->item($i)->parentNode->removeChild($tags->item($i));
        }
        $html = $doc->saveHTML();
        $html = preg_replace('/<!DOCTYPE.*?<html>.*?<body>/ims', '', $html);

        return str_replace('</body></html>', '', $html);
    }

    public function makeContentSearchable(string $html): string
    {
        // Header tags don't render too well in plain text
        // making the full size don't look good either so we're just removing them
        $tagsToRemove = [
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

    /**
     * @throws Exception
     */
    public function getWebsite(string $url): Collection
    {
        $readability = new Readability(new ReadabilityConfiguration());
        $url = $this->adjustUrl($url);
        $html = $this->fetchWebsite($url);

        try {
            $readability->parse($html);

            return collect([
                'image'   => $readability->getImage(),
                'author'  => $readability->getAuthor(),
                'excerpt' => $readability->getExcerpt(),
                'title'   => $readability->getTitle(),
                'html'    => $readability->getContent(),
            ]);
        } catch (ReadabilityParseException $e) {
            echo sprintf('Error processing text: %s', $e->getMessage());

            return collect([]);
        }
    }

    /**
     * Some types of urls need a bit of fudging to get the right content. For example
     * google docs files.
     */
    public function adjustUrl(string $url): string
    {
        $adjustedUrl = $url;
        // We want the end of the url to be `/export/html` for google docs files
        if (Str::contains($url, 'docs.google.com')) {
            // example: https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit
            $result = explode('/', $adjustedUrl);
            $removeString = $result[6] ?? '';
            $adjustedUrl = str_replace($removeString, '', $adjustedUrl);
            if ('/' === substr($adjustedUrl, -1)) {
                $adjustedUrl = substr($adjustedUrl, 0, -1);
            }
            $adjustedUrl .= '/export/html';
        }

        return $adjustedUrl;
    }
}
