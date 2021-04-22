<?php

namespace Tests\Utils\WebsiteExtraction;

use andreskrey\Readability\ParseException;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use Tests\TestCase;

class WebsiteExtractionHelperTest extends TestCase
{
    /**
     * @throws ParseException
     * @dataProvider parseHtmlProvider
     */
    public function testParseHtml(string $description, string $rawHtml, string $title, string $url, string $expectedDescription, string $expectedHtml, string $expectedTitle): void
    {
        $htmlDescription = '<meta name="description" content="'.$description.'">';
        $htmlTitle = '<title>'.$title.'</title>';
        $result = app(WebsiteExtractionHelper::class)->parseHtml($htmlDescription.$htmlTitle.$rawHtml, $url);
        self::assertEquals($expectedDescription, $result->get('excerpt'));
        self::assertEquals($expectedTitle, $result->get('title'));
        self::assertEquals($expectedHtml, $result->get('html'));
    }

    public function parseHtmlProvider(): array
    {
        $descriptions = [
            'My cool description',
        ];
        $html = [
           '<h1>I like this</h1><p>Do you?</p>',
        ];
        $titles = [
            'My cool title',
        ];

        return [
            [$descriptions[0], $html[0], $titles[0], 'https://googledoc.com', $descriptions[0], '<p>Do you?</p>', $titles[0]],
            [$descriptions[0], $html[0], $titles[0], 'https://www.notion.so/stuff', 'Do you?', '<p>Do you?</p>', $titles[0]],
        ];
    }
}
