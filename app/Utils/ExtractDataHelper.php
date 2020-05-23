<?php

namespace App\Utils;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException as ReadabilityParseException;
use andreskrey\Readability\Readability;
use App\Utils\TikaWebClient\TikaWebClientInterface;
use Html2Text\Html2Text;
use Illuminate\Support\Collection;

class ExtractDataHelper
{
    private TikaWebClientInterface $client;

    public function __construct(TikaWebClientInterface $client)
    {
        $this->client = $client;
    }

    public function getData(string $file): array
    {
        $data = collect(json_decode(json_encode($this->client->getMetadata($file)), true));
        $meta = collect($data->get('meta'));
        $content = $this->client->getText($file);

        return [
            'title'                       => $meta->get('dc:title'),
            'keyword'                     => $meta->get('meta:keyword'),
            'author'                      => $meta->get('meta:author'),
            'last_author'                 => $meta->get('meta:last-author'),
            'encoding'                    => $meta->get('encoding'),
            'comment'                     => $meta->get('comment'),
            'language'                    => $meta->get('language'),
            'subject'                     => $meta->get('cp:subject'),
            'revisions'                   => $meta->get('cp:revision'),
            'created'                     => $meta->get('meta:creation-date'),
            'modified'                    => $meta->get('Last-Modified'),
            'print_date'                  => $meta->get('meta:print-date'),
            'save_date'                   => $meta->get('meta:save-date'),
            'line_count'                  => $meta->get('meta:line-count'),
            'page_count'                  => $meta->get('meta:page-count') ?? $data->get('pages'),
            'paragraph_count'             => $meta->get('meta:paragraph-count'),
            'word_count'                  => $meta->get('meta:word-count'),
            'character_count'             => $meta->get('meta:character-count'),
            'character_count_with_spaces' => $meta->get('meta:character-count-with-spaces'),
            'width'                       => $data->get('width'),
            'height'                      => $data->get('height'),
            'copyright'                   => $data->get('Copyright'),
            'content'                     => $content,
        ];
    }

    /**
     * Get file data from a stream.
     *
     * @param [type] $content
     */
    public function getFileData(string $mimeType, $content): Collection
    {
        $extension = FileHelper::mimeToExtension($mimeType);
        if (! $extension) {
            return [];
        }

        $filename = \Str::random(16).'.'.$extension;

        \Storage::put($filename, $content);

        try {
            $data = $this->getData(base_path().'/storage/app/'.$filename);
        } catch (\Exception $e) {
            \Log::notice('We couldn\'t extract the data from a file with type '.$mimeType);

            return [];
        }

        \Storage::delete($filename);

        return collect($data);
    }

    public function getWebsite(string $url)
    {
        $readability = new Readability(new ReadabilityConfiguration());
        $html = file_get_contents($url);

        try {
            $readability->parse($html);

            return (new Html2Text($readability))->getText();
        } catch (ReadabilityParseException $e) {
            echo sprintf('Error processing text: %s', $e->getMessage());
        }
    }
}
