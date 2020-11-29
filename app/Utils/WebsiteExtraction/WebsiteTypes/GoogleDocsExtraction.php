<?php

namespace App\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionInterface;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// Only links that are links to a google doc type of file sheet/doc/slide/form
// will come here
class GoogleDocsExtraction extends BaseExtraction implements WebsiteExtractionInterface
{
    /**
     * @throws Exception
     */
    public function getWebsite(): Collection
    {
        // This will get everything but the content correctly
        $baseDoc = $this->websiteExtractionHelper->parseHtml($this->websiteExtractionHelper->simpleFetch($this->url));

        // Changing the url to the exported html will get the content correctly
        $htmlExportUrl = $this->toHtmlExport($this->url);
        $content = $this->websiteExtractionHelper->parseHtml($this->websiteExtractionHelper->simpleFetch($htmlExportUrl));

        return collect([
            'image'   => $baseDoc->get('image'),
            'author'  => $baseDoc->get('author'),
            'excerpt' => $baseDoc->get('excerpt'),
            'title'   => $baseDoc->get('title'),
            'html'    => $content->get('html'),
        ]);
    }

    /**
     * To get the content from a public google doc file we need to change the link to an html export link.
     */
    public function toHtmlExport(string $url): string
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
