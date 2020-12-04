<?php

namespace App\Utils;

use DOMDocument;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class ExtractDataHelper
{
    private const MAX_TITLE_SIZE = 60;
    private const MAX_EXCERPT_SIZE = 200;
    private TikaWebClientWrapper $client;
    private FileHelper $fileHelper;

    public function __construct(
        TikaWebClientWrapper $client,
        FileHelper $fileHelper
    ) {
        $this->client = $client;
        $this->fileHelper = $fileHelper;
    }

    public function getTitleFromHeadingTag(string $html): string
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html);

        $acceptedTags = [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p',
        ];

        foreach ($acceptedTags as $tag) {
            $tags = $doc->getElementsByTagName($tag);
            if ($tags->length > 0) {
                for ($i = 0; $i < $tags->length; ++$i) {
                    $value = $tags->item($i)->nodeValue;
                    if (! $value) {
                        continue;
                    }
                    if ('p' === $tag) {
                        $value = Str::truncateOnWord($value, self::MAX_TITLE_SIZE);
                    }

                    return Str::toSingleSpace(trim($value, '?!.,;/'));
                }
            }
        }

        return '';
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function getData(string $file): array
    {
        $data = collect(json_decode(
            json_encode(
                $this->client->getMetaData($file), JSON_THROW_ON_ERROR
            ),
            true, 512, JSON_THROW_ON_ERROR)
        );
        $meta = collect($data->get('meta'));

        $content = trim(Str::purifyHtml($this->client->getHtml($file)));

        $title = (string) Str::toSingleSpace(Str::of(trim($meta->get('dc:title')))->snake()->replace('_', ' ')->title());
        if (! $title || Str::hasExtension($title)) {
            $title = $this->getTitleFromHeadingTag($content);
        }

        $excerpt = $content ? Str::htmlToText($content, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'ul', 'ol']) : '';
        $excerpt = Str::truncateOnWord(trim(str_replace($title, '', Str::toSingleSpace(trim($excerpt)))), self::MAX_EXCERPT_SIZE);

        return [
            'title'                       => $title,
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
            'excerpt'                     => $excerpt,
            'content'                     => trim($content),
        ];
    }

    /**
     * Some files are typically too large and don't return content anyways. For those files
     * we should just exclude them altogether from apache tika.
     */
    public static function isExcluded(string $mimeType): bool
    {
        $excludedTypes = collect(['zip', 'audio', 'video', 'sql']);

        return $excludedTypes->contains(static function ($value) use ($mimeType) {
            return Str::contains($mimeType, $value);
        });
    }

    /**
     * @param $content
     */
    public function getFileData(string $mimeType, $content): Collection
    {
        $extension = $this->fileHelper->mimeToExtension($mimeType);
        if (! $extension) {
            Log::notice("The mimetype $mimeType could not be mapped to an extension.");

            return collect([]);
        }

        if (self::isExcluded($mimeType)) {
            return collect([]);
        }

        $filename = Str::random(16).'.'.$extension;

        Storage::put($filename, $content);
        try {
            $data = $this->getData(base_path().'/storage/app/'.$filename);
        } catch (Exception $e) {
            Log::notice("We couldn't extract the data from a file with type $mimeType");
            Storage::delete($filename);

            return collect([]);
        }

        Storage::delete($filename);

        return collect($data);
    }
}
