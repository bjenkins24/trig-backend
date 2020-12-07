<?php

namespace App\Utils;

use DOMDocument;
use Exception;
use League\HTMLToMarkdown\HtmlConverter;
use Mews\Purifier\Facades\Purifier;

class StrCustom
{
    public const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'em', 'strong', 'i', 'b', 'table', 'tr', 'td', 'th', 'tbody', 'thead', 'ul', 'ol', 'li', 'pre', 'br', 'blockquote', 'code',
    ];

    public static function truncateOnWord(string $string, int $maxChars): string
    {
        $parts = preg_split('/([\s\n\r]+)/u', $string, null, PREG_SPLIT_DELIM_CAPTURE);
        $partsCount = count($parts);

        $lastPart = 0;
        for ($length = 0; $lastPart < $partsCount; ++$lastPart) {
            $length += strlen($parts[$lastPart]);
            if ($length > $maxChars) {
                break;
            }
        }

        return trim(implode(array_slice($parts, 0, $lastPart)));
    }

    public static function toSingleSpace(string $string): string
    {
        return preg_replace('!\s+!', ' ', $string);
    }

    public static function hasExtension(string $string): bool
    {
        return (bool) preg_match('/\.[a-zA-Z]{3}$/', $string);
    }

    public static function removeTag(string $html, string $tag): string
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

    public static function htmlToText(string $html, array $tagsToRemove = []): string
    {
        if (! $html) {
            return '';
        }
        if (count($tagsToRemove) > 0) {
            foreach ($tagsToRemove as $tag) {
                $html = self::removeTag($html, $tag);
            }
        }

        return strip_tags($html);
    }

    public static function removeLineBreaks(string $string): string
    {
        return str_replace(["\r", "\n"], '', $string);
    }

    public static function htmlToMarkdown(string $html, array $tagsToRemove = []): string
    {
        if (! $html) {
            return '';
        }
        if (count($tagsToRemove) > 0) {
            foreach ($tagsToRemove as $tag) {
                $html = self::removeTag($html, $tag);
            }
        }

        // No anchor here... It's because of the use case of search, makes this function
        // not really named very well
        $html = Purifier::clean($html, [
            'HTML.Allowed' => implode(',', self::ALLOWED_TAGS),
        ]);

        return (new HtmlConverter(['strip_tags' => true]))->convert($html);
    }

    public static function purifyHtml(string $string)
    {
        return Purifier::clean($string, [
            'HTML.Allowed' => implode(',', self::ALLOWED_TAGS).',a[href]',
        ]);
    }
}
