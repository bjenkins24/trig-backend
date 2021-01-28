<?php

namespace Tests\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class GenericExtractionTest extends TestCase
{
    use MockWebsiteTrait;

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function testGetWebsite(): void
    {
        $url = 'https://medium.com/@sachinrekhi/designing-your-products-continuous-feedback-loop-4a7bb31141fe';
        $mockHtml = '<div id="my_cool_id">My cool html</div>';
        $mockWebsite = $this->getMockWebsite($mockHtml);

        $this->mock(WebsiteExtractionHelper::class, function ($mock) use ($url, $mockWebsite, $mockHtml) {
            $mock->shouldReceive('fullFetch')->with($url, '35000')->andReturn($mockWebsite);
            $mock->shouldReceive('parseHtml')->with($mockHtml)->andReturn($this->getMockParseHtml($mockWebsite));
        });
        $website = app(WebsiteExtractionFactory::class)->make($url)->getWebsite();

        self::assertEquals($website, $mockWebsite);
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function testGetWebsiteOnRetry(): void
    {
        $url = 'https://medium.com/@sachinrekhi/designing-your-products-continuous-feedback-loop-4a7bb31141fe';
        $content = 'my html';
        $mockWebsite = $this->getMockWebsite($content);
        $this->mock(WebsiteExtractionHelper::class, function ($mock) use ($content, $mockWebsite) {
            $mock->shouldReceive('fullFetch')->andReturn($mockWebsite);
            $mock->shouldReceive('parseHtml')->with($content)->andReturn($this->getMockParseHtml($mockWebsite));
        });
        $website = app(WebsiteExtractionFactory::class)->make($url)->getWebsite(1);
        self::assertEquals($mockWebsite, $website);

        $this->mock(WebsiteExtractionHelper::class, static function ($mock) use ($url, $mockWebsite) {
            $mock->shouldReceive('downloadAndExtract')->with($url)->andReturn($mockWebsite);
        });

        $website = app(WebsiteExtractionFactory::class)->make($url)->getWebsite(2);
        self::assertEquals($mockWebsite, $website);
    }
}
