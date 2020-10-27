<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Jobs\CardDedupe;
use App\Models\Capability;
use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\CardType;
use App\Models\Person;
use App\Modules\Card\Integrations\Google\GoogleContent;
use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Utils\ExtractDataHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\Support\Traits\SyncCardsTrait;
use Tests\TestCase;
use Tests\Utils\ExtractDataHelperTest;

class SyncCardsTest extends TestCase
{
    use CreateOauthConnection;
    use SyncCardsTrait;

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testNoSyncWhenUpToDate(): void
    {
        $initialData = $this->getMockData();
        $title = 'My super cool title';
        $initialData[0]['data']['title'] = $title;
        $initialData[0]['data']['actual_modified_at'] = '1701-01-01 10:35:00';
        [$syncCards, $data, $user] = $this->getSetup(null, $initialData);

        $cardIntegration = CardIntegration::find(1);
        $cardIntegration->foreign_id = $data[0]['data']['foreign_id'];
        $cardIntegration->save();

        $syncCards->syncCards($user, time());

        $this->assertDatabaseMissing('cards', [
            'title' => $title,
        ]);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testSyncCardsEmpty(): void
    {
        [$syncCards, $data, $user] = $this->getSetup(null, []);
        $result = $syncCards->syncCards($user, time());
        self::assertFalse($result);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testSyncCards(): void
    {
        [$syncCards, $data, $user] = $this->getSetup();

        $card = $data[0]['data'];

        Storage::shouldReceive('put')->andReturn(true)->once();
        Storage::shouldReceive('url')->andReturn('/my_cool_thing.jpg')->twice();

        $syncCards->syncCards($user, time());

        $cardType = CardType::where('name', '=', $card['card_type'])->first();
        $newCard = Card::where('title', '=', $card['title'])->first();

        $this->assertDatabaseHas('card_types', [
            'name' => $card['card_type'],
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'card_id'    => $newCard->id,
            'foreign_id' => $card['foreign_id'],
        ]);

        $this->assertDatabaseHas('cards', [
            'id'                 => $newCard->id,
            'user_id'            => $card['user_id'],
            'card_type_id'       => $cardType->id,
            'title'              => $card['title'],
            'description'        => $card['description'],
            'url'                => $card['url'],
            'actual_created_at'  => $card['actual_created_at'],
            'actual_modified_at' => $card['actual_modified_at'],
            'image'              => Config::get('app.url').Storage::url(SyncCardsIntegration::IMAGE_FOLDER.'/'.$newCard->id),
            'image_width'        => $this->getMockThumbnail()->get('width'),
            'image_height'       => $this->getMockThumbnail()->get('height'),
        ]);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testDeleteCard(): void
    {
        [$syncCards, $data, $user] = $this->getSetup();
        $cardData = $data[0]['data'];
        $card = Card::where('title', '=', $cardData['title'])->first();
        self::assertNull($card);

        $syncCards->syncCards($user, time());

        $card = Card::where('title', '=', $cardData['title'])->first();
        self::assertNotNull($card);

        $data[0]['data']['delete'] = true;
        [$syncCards, $data, $user] = $this->getSetup(null, $data);
        $syncCards->syncCards($user, time());

        // It was deleted!
        $this->assertDatabaseMissing('cards', [
            'id' => $card->id,
        ]);

        $syncCards->syncCards($user, time());

        // It's already been deleted, but we won't insert it again
        $card = Card::where('title', '=', $cardData['title'])->first();
        self::assertNull($card);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testSyncPermissions(): void
    {
        $initialData = $this->getMockData();
        $initialData[0]['permissions'] = [
            'users'      => [
                [
                    'email'      => 'coolperson@gmail.com',
                    'capability' => 'writer',
                ],
                [
                    'email'      => 'coolperson2@gmail.com',
                    'capability' => 'reader',
                ],
            ],
            'link_share' => [
                    [
                        'type'       => 'public',
                        'capability' => 'reader',
                    ],
                    [
                        'type'       => 'anyone',
                        'capability' => 'writer',
                    ],
                    [
                        'type'       => 'anyone_organization',
                        'capability' => 'reader',
                    ],
            ],
        ];
        [$syncCards, $data, $user] = $this->getSetup(null, $initialData);

        $syncCards->syncCards($user, time());

        $card = Card::where('title', '=', $initialData[0]['data']['title'])->first();
        $person1 = Person::where('email', '=', $data[0]['permissions']['users'][0]['email'])->first();
        $person2 = Person::where('email', '=', $data[0]['permissions']['users'][1]['email'])->first();

        $writerId = Capability::where('name', '=', 'writer')->first()->id;
        $readerId = Capability::where('name', '=', 'reader')->first()->id;

        $this->assertDatabaseHas('people', [
          'email' => $data[0]['permissions']['users'][0]['email'],
        ]);
        $this->assertDatabaseHas('people', [
            'email' => $data[0]['permissions']['users'][1]['email'],
        ]);
        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => Card::class,
            'permissionable_id'   => $card->id,
            'capability_id'       => $writerId,
        ]);
        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => Card::class,
            'permissionable_id'   => $card->id,
            'capability_id'       => $readerId,
        ]);

        $this->assertDatabaseHas('permission_types', [
            'typeable_id'   => $person2->id,
            'typeable_type' => Person::class,
        ]);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws JsonException
     */
    public function testSaveCardData(): void
    {
        Queue::fake();
        $this->mock(GoogleContent::class, static function ($mock) {
            $mock->shouldReceive('getCardContent')->andReturn('my cool content');
        });

        $cardData = (new ExtractDataHelperTest())->getMockDataResult('my cool content');

        $cardData->put('created', Carbon::create($cardData->get('created'))->toDateTimeString());
        $cardData->put('modified', Carbon::create($cardData->get('modified'))->toDateTimeString());
        $cardData->put('print_date', Carbon::create($cardData->get('print_date'))->toDateTimeString());
        $cardData->put('save_date', Carbon::create($cardData->get('save_date'))->toDateTimeString());
        $content = $cardData->get('content');

        $this->mock(ExtractDataHelper::class, static function ($mock) use ($cardData) {
            $mock->shouldReceive('getFileData')->andReturn($cardData)->once();
        });

        [$syncCards, $data, $user] = $this->getSetup();
        $syncCards->syncCards($user);

        $card = Card::find(1);

        $syncCards->saveCardData($card);

        $cardData->forget('content');
        $cardData = $cardData->reject(static function ($value) {
            return ! $value;
        });

        $this->assertDatabaseHas('cards', [
            'content'    => $content,
            'properties' => $this->castToJson($cardData->toArray()),
        ]);
        Queue::assertPushed(CardDedupe::class, 1);
    }
}
