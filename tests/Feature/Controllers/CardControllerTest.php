<?php

namespace Tests\Feature\Controllers;

use App\Jobs\SaveCardData;
use App\Jobs\SaveCardDataInitial;
use App\Models\CardType;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\CardSync\CardSyncRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use JsonException;
use Storage;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws JsonException
     */
    public function testCreateCard(): void
    {
        Queue::fake();

        Carbon::setTestNow('2020-11-20 00:00:20');
        $now = Carbon::now();
        $data = [
            'url'                => 'https://google.com',
            'title'              => 'Google',
            'description'        => 'Description',
            'content'            => 'content',
            'createdAt'          => $now,
            'updatedAt'          => $now,
        ];
        $response = $this->client('POST', 'card', $data);
        // Check if the response returns an id
        self::assertNotEmpty($this->getResponseData($response)->get('id'));

        $data['actual_created_at'] = $now->toDateTimeString();
        $data['actual_updated_at'] = $now->toDateTimeString();
        $cardTypeId = CardType::where('name', '=', 'link')->first()->id;
        $data['card_type_id'] = $cardTypeId;
        unset($data['createdAt'], $data['updatedAt']);

        $this->assertDatabaseHas('cards', $data);
        Queue::assertPushed(SaveCardDataInitial::class, 1);
    }

    /**
     * @throws JsonException
     */
    public function testCreateCardNoProtocol(): void
    {
        Queue::fake();
        $response = $this->client('POST', 'card', ['url' => 'google.com']);
        self::assertEquals('http://google.com', $this->getResponseData($response)->get('url'));
        $this->assertDatabaseHas('cards', [
            'url' => 'http://google.com',
        ]);
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

    /**
     * @throws JsonException
     */
    public function testCreateCardNoWorkspace(): void
    {
        $userId = 1;
        User::find($userId)->workspaces()->create([
            'name' => 'second 1',
        ]);
        $response = $this->client('POST', 'card', ['url' => 'http://google.com', 'user_id' => $userId]);

        self::assertEquals('bad_request', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(422, $response->getStatusCode());
    }

    public function testCreateCardDifferentCardType(): void
    {
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
        Queue::fake();
        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $response = $this->client('GET', "card/$cardId");
        self::assertEquals($cardId, $this->getResponseData($response)->get('id'));
    }

    /**
     * @throws JsonException
     */
    public function testGetAll(): void
    {
        $this->mock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('searchCards')->andReturn(collect([
                'cards'   => 'cards',
                'meta'    => 'meta',
                'filters' => 'filters',
            ]));
        });
        $response = $this->client('GET', 'cards');
        self::assertEquals('cards', $this->getResponseData($response, 'data')->get(0));
        self::assertEquals('meta', $this->getResponseData($response, 'meta')->get(0));
        self::assertEquals('filters', $this->getResponseData($response, 'filters')->get(0));
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
    public function testUpdateCardSaveData(): void
    {
        Queue::fake();

        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $newCard = $this->getResponseData($response);

        // This is what would happen if we weren't faking the queue
        app(CardSyncRepository::class)->create([
            'card_id' => $newCard->get('id'),
            'status'  => 1,
        ]);

        $now = Carbon::now();

        $newData = [
            'id'                 => $newCard->get('id'),
            'url'                => 'https://newurl.com',
            'title'              => 'Cool new url',
            'description'        => 'cool new Description',
            'content'            => 'cool new content',
            'createdAt'          => $now,
            'updatedAt'          => $now,
            'isFavorited'        => true,
        ];

        $this->client('PATCH', 'card', $newData);

        Queue::assertPushed(SaveCardDataInitial::class, 1);
        // It shouldn't sync because it already did when we created it above
        Queue::assertPushed(SaveCardData::class, 0);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardSuccess(): void
    {
        Queue::fake();

        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $newCard = $this->getResponseData($response);

        $this->assertDatabaseMissing('card_favorites', [
            'card_id' => $newCard->get('id'),
            'user_id' => $newCard->get('user_id'),
        ]);

        // Since we're faking SaveCardData it's not going to say it saved. so let's save it manually here for the test
        // to mimic what would happen after a post normally
        app(CardSyncRepository::class)->create([
            'card_id' => $newCard->get('id'),
            'status'  => 1,
        ]);

        $now = Carbon::now();
        $favoritedById = 1;

        $newData = [
            'id'                  => $newCard->get('id'),
            'url'                 => 'https://newurl.com',
            'title'               => 'Cool new url',
            'description'         => 'cool new Description',
            'content'             => 'cool new content',
            'created_at'          => $now,
            'updated_at'          => $now,
            'favorited_by'        => $favoritedById,
        ];

        $response = $this->client('PATCH', 'card', $newData);
        self::assertEquals(204, $response->getStatusCode());

        $data = $newData;
        $data['actual_created_at'] = $now->toDateTimeString();
        $data['actual_updated_at'] = $now->toDateTimeString();
        $data['total_favorites'] = 1;
        unset($data['created_at'], $data['updated_at'], $data['favorited_by']);

        $this->assertDatabaseHas('cards', $data);
        $this->assertDatabaseHas('card_favorites', [
            'card_id' => $newCard->get('id'),
            'user_id' => $favoritedById,
        ]);
        Queue::assertPushed(SaveCardDataInitial::class, 1);
        Queue::assertPushed(SaveCardData::class, 0);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardExists(): void
    {
        Queue::fake();

        $firstUrl = 'http://testurl.com';
        $this->client('POST', 'card', ['url' => $firstUrl]);
        $response = $this->client('POST', 'card', ['url' => 'http://different.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $response = $this->client('PATCH', 'card', ['id' => $cardId, 'url' => $firstUrl]);

        self::assertEquals('exists', $this->getResponseData($response, 'error')->get('error'));
    }

    public function testCreateCardExists(): void
    {
        Queue::fake();

        $firstUrl = 'http://testurl.com';
        $this->client('POST', 'card', ['url' => $firstUrl]);
        $response = $this->client('POST', 'card', ['url' => $firstUrl]);

        self::assertSame($response->json()['data']['url'], $firstUrl);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardNullFields(): void
    {
        Queue::fake();
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
        $this->client('PATCH', 'card', $update);
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
        $response = $this->client('PATCH', 'card', ['id' => '12000']);
        self::assertEquals('not_found', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(400, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCardForbidden(): void
    {
        $response = $this->client('PATCH', 'card', ['id' => '5']);
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
        Storage::fake();
        $response = $this->client('POST', 'card', ['url' => 'http://testurl.com']);
        $cardId = $this->getResponseData($response)->get('id');
        $this->assertDatabaseHas('cards', [
            'id' => $cardId,
        ]);
        $response = $this->client('DELETE', 'card/'.$cardId);
        self::assertEquals(204, $response->getStatusCode());
        $this->assertDatabaseMissing('cards', [
            'id' => $cardId,
        ]);
    }
}
