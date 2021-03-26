<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GetContentFromScreenshot;
use App\Jobs\GetTags;
use App\Models\Card;
use App\Utils\ExtractDataHelper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GetContentFromScreenshotTest extends TestCase
{
    use RefreshDatabase;

    public function testGetContentFromScreenshot(): void
    {
        Queue::fake();
        $card = Card::find(1);
        $card->setProperties(['screenshot' => 'image']);
        $card->save();

        $mockData = [
            'content' => 'hello there',
            'excerpt' => 'my excerpt',
        ];

        $this->mock(ExtractDataHelper::class, static function ($mock) use ($mockData) {
            $mock->shouldReceive('getData')->andReturn($mockData);
        });

        $getContentFromScreenshot = new GetContentFromScreenshot($card);
        $result = $getContentFromScreenshot->handle();

        Queue::assertPushed(GetTags::class, 1);
        $this->assertDatabaseHas('cards', ['id' => $card->id, 'content' => $mockData['content']]);
        self::assertTrue($result);
    }

    public function testGetContentFromScreenshotFail(): void
    {
        $card = Card::find(1);

        $getContentFromScreenshot = new GetContentFromScreenshot($card);
        $result = $getContentFromScreenshot->handle();
        self::assertFalse($result);
    }

    public function testGetContentFromScreenshotError(): void
    {
        $card = Card::find(1);

        $this->mock(ExtractDataHelper::class, static function ($mock) {
            $mock->shouldReceive('getData')->andThrow(new Exception('hello'));
        });

        $getContentFromScreenshot = new GetContentFromScreenshot($card);
        $result = $getContentFromScreenshot->handle();
        self::assertFalse($result);
    }
}
