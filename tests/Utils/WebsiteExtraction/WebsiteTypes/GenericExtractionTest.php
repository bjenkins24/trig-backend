<?php

namespace Tests\Utils\WebsiteExtraction\WebsiteTypes;

use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class GenericExtractionTest extends TestCase
{
    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function testGetWebsite(): void
    {
        $url = 'https://medium.com/@sachinrekhi/designing-your-products-continuous-feedback-loop-4a7bb31141fe';
        $content = collect([
            'image'   => 'my image',
            'author'  => 'my author',
            'excerpt' => 'my excerpt',
            'title'   => 'my title',
            'html'    => 'my html',
        ]);
        $mockHtml = '<div id="my_cool_id">My cool html</div>';
        $this->mock(WebsiteExtractionHelper::class, static function ($mock) use ($url, $mockHtml, $content) {
            $mock->shouldReceive('fullFetch')->with($url)->andReturn($mockHtml);
            $mock->shouldReceive('parseHtml')->with($mockHtml)->andReturn($content);
        });
        $website = app(WebsiteExtractionFactory::class)->make($url)->getWebsite();
        self::assertEquals($content, $website);
    }
}
