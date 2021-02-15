<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GetContentFromScreenshot;
use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Utils\ExtractDataHelper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetContentFromScreenshotTest extends TestCase
{
    use RefreshDatabase;

    public function testGetContentFromImage(): void
    {
        $card = Card::find(1);
        app(CardRepository::class)->setProperties($card, ['full_screenshot' => 'image']);
        $card->save();

        $mockData = [
            'content' => 'hello there',
            'excerpt' => 'my excerpt',
        ];

        $this->mock(ExtractDataHelper::class, static function ($mock) use ($mockData) {
            $mock->shouldReceive('getData')->andReturn($mockData);
        });

        $getContentFromImageJob = new GetContentFromScreenshot($card);
        $result = $getContentFromImageJob->handle();

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'content' => $mockData['content']]);
        self::assertTrue($result);
    }

    public function testGetContentFromImageFail(): void
    {
        $card = Card::find(1);

        $getContentFromImageJob = new GetContentFromScreenshot($card);
        $result = $getContentFromImageJob->handle();
        self::assertFalse($result);
    }

    public function testGetContentFromImageError(): void
    {
        $card = Card::find(1);

        $this->mock(ExtractDataHelper::class, static function ($mock) {
            $mock->shouldReceive('getData')->andThrow(new Exception('hello'));
        });

        $getContentFromImageJob = new GetContentFromScreenshot($card);
        $result = $getContentFromImageJob->handle();
        self::assertFalse($result);
    }
}
