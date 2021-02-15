<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveImage;
use App\Models\Card;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveImageTest extends TestCase
{
    use RefreshDatabase;

    public function testSaveImageSuccess(): void
    {
        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail');
        });
        $syncCards = new SaveImage('hello', 'goodbye', Card::find(1));
        $result = $syncCards->handle();
        self::assertTrue($result);
    }

    public function testSaveImageFail(): void
    {
        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail')->andThrow(new Exception('test'));
        });
        $syncCards = new SaveImage('hello', 'goodbye', Card::find(1));
        $result = $syncCards->handle();
        self::assertFalse($result);
    }
}
