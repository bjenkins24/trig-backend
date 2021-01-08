<?php

namespace Tests\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\ExtractDataHelper;
use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use App\Utils\WebsiteExtraction\WebsiteTypes\GoogleDocsExtraction;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class GoogleDocsExtractionTest extends TestCase
{
    use MockWebsiteTrait;
//    /**
//     * @throws BindingResolutionException
//     * @group n
//     */
//    public function testOneThing(): void
//    {
//        $website = app(WebsiteExtractionFactory::class)->make('https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit?usp=sharing')->getWebsite();
//        dd($website);
//    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function testGetWebsite(): void
    {
        $url = 'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit?usp=sharing';
        $mockWebsite = $this->getMockWebsite('raw html');
        $parseHtml = $this->getMockParseHtml($mockWebsite);
        $this->mock(WebsiteExtractionHelper::class, static function ($mock) use ($parseHtml, $mockWebsite) {
            $mock->shouldReceive('parseHtml')->andReturn($parseHtml);
            $mock->shouldReceive('simpleFetch')->andReturn($mockWebsite);
        });
        $website = app(WebsiteExtractionFactory::class)->make($url)->getWebsite();
        self::assertEquals($mockWebsite, $website);
    }

    /**
     * @dataProvider toHtmlExportProvider
     */
    public function testToHtmlExport(string $givenUrl, string $expectedUrl): void
    {
        $this->mock(ExtractDataHelper::class);
        $newLink = app(GoogleDocsExtraction::class)->toHtmlExport($givenUrl);
        self::assertEquals(
            $expectedUrl,
            $newLink
        );
    }

    public function toHtmlExportProvider(): array
    {
        return [
            [
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit?usp=sharing',
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/export/html',
            ],
            [
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs',
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/export/html',
            ],
            [
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit',
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/export/html',
            ],
        ];
    }
}
