<?php

namespace App\Utils;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException as ReadabilityParseException;
use andreskrey\Readability\Readability;
use Exception;
use Html2Text\Html2Text;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class ExtractDataHelper
{
    private TikaWebClientWrapper $client;
    private FileHelper $fileHelper;

    public function __construct(
        TikaWebClientWrapper $client,
        FileHelper $fileHelper
    ) {
        $this->client = $client;
        $this->fileHelper = $fileHelper;
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

    public function getWebsite(string $url): Collection
    {
        $readability = new Readability(new ReadabilityConfiguration());
        $html = $this->fileHelper->fileGetContents($url);

        try {
            $readability->parse($html);

            return collect([
                'image'   => $readability->getImage(),
                'author'  => $readability->getAuthor(),
                'excerpt' => $readability->getExcerpt(),
                'title'   => $readability->getTitle(),
                'text'    => (new Html2Text($readability))->getText(),
                'html'    => $readability->getContent(),
            ]);
        } catch (ReadabilityParseException $e) {
            echo sprintf('Error processing text: %s', $e->getMessage());

            return collect([]);
        }
    }
}
