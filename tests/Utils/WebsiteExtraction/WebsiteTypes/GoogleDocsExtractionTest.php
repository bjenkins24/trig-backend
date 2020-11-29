<?php

namespace Tests\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use App\Utils\WebsiteExtraction\WebsiteTypes\GoogleDocsExtraction;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class GoogleDocsExtractionTest extends TestCase
{
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
        $content = collect([
            'image'   => 'my image',
            'author'  => 'my author',
            'excerpt' => 'my excerpt',
            'title'   => 'my title',
            'html'    => 'my html',
        ]);
        $this->mock(WebsiteExtractionHelper::class, static function ($mock) use ($content) {
            $mock->shouldReceive('parseHtml')->andReturn($content);
            $mock->shouldReceive('simpleFetch');
        });
        $website = app(WebsiteExtractionFactory::class)->make($url)->getWebsite();
        self::assertEquals($content, $website);
    }

    /**
     * @dataProvider toHtmlExportProvider
     */
    public function testToHtmlExport(string $givenUrl, string $expectedUrl): void
    {
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
