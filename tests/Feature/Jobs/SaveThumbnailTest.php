<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SaveThumbnails;
use App\Models\Card;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function testSaveImageSuccess(): void
    {
        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail');
        });
        $syncCards = new SaveThumbnails(collect(['image' => 'sds']), Card::find(1));
        $result = $syncCards->handle();
        self::assertTrue($result);
    }

    public function testSaveImageFail(): void
    {
        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail')->andThrow(new Exception('test'));
        });
        $syncCards = new SaveThumbnails(collect(['image' => 'sds']), Card::find(1));
        $result = $syncCards->handle();
        self::assertFalse($result);
    }
}
