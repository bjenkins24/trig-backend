<?php

namespace Tests\Utils\WebsiteExtraction;

use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteTypes\GenericExtraction;
use App\Utils\WebsiteExtraction\WebsiteTypes\GoogleDocsExtraction;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class WebsiteExtractionFactoryTest extends TestCase
{
    /**
     * @throws BindingResolutionException
     * @dataProvider urlProvider
     */
    public function testMakeWebsiteType(string $url, string $extractionClass): void
    {
        $actualClass = app(WebsiteExtractionFactory::class)->make($url);
        self::assertInstanceOf($extractionClass, $actualClass);
    }

    public function urlProvider(): array
    {
        return [
            ['https://docs.google.com/document/d/1Fuo8bFnEjdbhkgYLEwiApscA5aFKemNRBfHYhuZwzFk/edit', GoogleDocsExtraction::class],
            ['https://medium.com/@sachinrekhi/designing-your-products-continuous-feedback-loop-4a7bb31141fe', GenericExtraction::class],
        ];
    }
}
