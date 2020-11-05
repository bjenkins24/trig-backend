<?php

namespace Tests\Feature\Controllers;

use App\Jobs\SaveCardData;
use Illuminate\Support\Facades\Queue;
use JsonException;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testCreateCard(): void
    {
        $this->refreshDb();
        Queue::fake();
        $url = 'https://google.com';
        $response = $this->client('POST', 'card/create', [
            'url' => $url,
        ]);
        // Check if the response returns the id
        self::assertEquals(6, json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']['id']);
        $this->assertDatabaseHas('cards', [
            'url'   => $url,
            'title' => $url,
        ]);
        Queue::assertPushed(SaveCardData::class, 1);
    }
}
