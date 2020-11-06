<?php

namespace Tests\Feature\Controllers;

use App\Jobs\SaveCardData;
use App\Models\CardType;
use App\Modules\Card\CardRepository;
use Illuminate\Support\Carbon;
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

        $now = Carbon::now();
        $data = [
            'url'                => 'https://google.com',
            'title'              => 'Google',
            'description'        => 'Description',
            'content'            => 'content',
            'createdAt'          => $now,
            'modifiedAt'         => $now,
        ];
        $response = $this->client('POST', 'card', $data);
        // Check if the response returns the id
        self::assertEquals(6, $this->getResponseData($response)->get('id'));

        $data['actual_created_at'] = $now->toDateTimeString();
        $data['actual_modified_at'] = $now->toDateTimeString();
        $cardTypeId = CardType::where('name', '=', 'link')->first()->id;
        $data['card_type_id'] = $cardTypeId;
        unset($data['createdAt'], $data['modifiedAt']);

        $this->assertDatabaseHas('cards', $data);
        Queue::assertPushed(SaveCardData::class, 1);
    }

    /**
     * @throws JsonException
     */
    public function testCreateCardFail(): void
    {
        $this->mock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('updateOrInsert')->andReturn(null);
        });

        $response = $this->client('POST', 'card', ['url' => 'http://google.com']);

        self::assertEquals('unexpected', $this->getResponseData($response, 'error')->get('error'));
    }

    public function testCreateCardDifferentCardType(): void
    {
        $this->refreshDb();

        $data = [
            'url'       => 'http://google.com',
            'card_type' => 'twitter',
        ];
        $this->client('POST', 'card', $data);
        $cardTypeId = CardType::where('name', '=', 'twitter')->first()->id;

        $this->assertDatabaseHas('cards', [
            'url'          => $data['url'],
            'title'        => $data['url'],
            'card_type_id' => $cardTypeId,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testGetCardSuccess(): void
    {
        $this->refreshDb();
        Queue::fake();
        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $response = $this->client('GET', "card/$cardId");
        self::assertEquals($cardId, $this->getResponseData($response)->get('id'));
    }

    /**
     * @throws JsonException
     */
    public function testGetCardNotFound(): void
    {
        $response = $this->client('GET', 'card/100');
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
    }

    /**
     * @throws JsonException
     */
    public function testGetCardForbidden(): void
    {
        $response = $this->client('GET', 'card/5');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardSuccess(): void
    {
        $this->refreshDb();
        Queue::fake();

        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');

        $now = Carbon::now();

        $newData = [
            'id'                 => $cardId,
            'url'                => 'https://newurl.com',
            'title'              => 'Cool new url',
            'description'        => 'cool new Description',
            'content'            => 'cool new content',
            'createdAt'          => $now,
            'modifiedAt'         => $now,
        ];

        $response = $this->client('PUT', 'card', $newData);
        self::assertEquals($cardId, $this->getResponseData($response)->get('id'));

        $data['actual_created_at'] = $now->toDateTimeString();
        $data['actual_modified_at'] = $now->toDateTimeString();
        unset($data['createdAt'], $data['modifiedAt']);

        $this->assertDatabaseHas('cards', $data);
        Queue::assertPushed(SaveCardData::class, 2);
    }

    /**
     * @throws JsonException
     * @group n
     */
    public function testUpdateCardNullFields(): void
    {
        Queue::fake();
        $this->refreshDb();
        $original = [
            'url'         => 'https://asdasd.com',
            'title'       => 'old title',
            'content'     => 'good content',
            'description' => 'awesome description',
        ];

        $response = $this->client('POST', 'card', $original);
        $update = [
            'id'          => $this->getResponseData($response)->get('id'),
            'title'       => 'new title',
            'description' => null,
        ];
        $this->client('PUT', 'card', $update);
        $this->assertDatabaseHas('cards', [
            'url'         => $original['url'],
            'title'       => $update['title'],
            'content'     => $original['content'],
            'description' => null,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardNotFound(): void
    {
        $response = $this->client('PUT', 'card', ['id' => '12000']);
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(400, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardForbidden(): void
    {
        $response = $this->client('PUT', 'card', ['id' => '5']);
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCardNotFound(): void
    {
        $response = $this->client('DELETE', 'card/12000');
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(400, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCardForbidden(): void
    {
        $response = $this->client('DELETE', 'card/5');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCardSuccess(): void
    {
        Queue::fake();
        $this->refreshDb();
        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $this->assertDatabaseHas('cards', [
            'id' => $cardId,
        ]);
        $response = $this->client('DELETE', 'card/6');
        self::assertEquals(204, $response->getStatusCode());
        $this->assertDatabaseMissing('cards', [
            'id' => $cardId,
        ]);
    }
}
