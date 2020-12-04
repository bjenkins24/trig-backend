<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Jobs\CardDedupe;
use App\Models\Capability;
use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\CardType;
use App\Models\Person;
use App\Modules\Card\Integrations\Google\GoogleContent;
use App\Modules\CardSync\CardSyncRepository;
use App\Modules\CardType\CardTypeRepository;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use App\Utils\WebsiteExtraction\WebsiteTypes\GenericExtraction;
use Illuminate\Support\Facades\Queue;
use JsonException;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\Support\Traits\SyncCardsTrait;
use Tests\TestCase;

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
        $initialData[0]['data']['actual_updated_at'] = '1701-01-01 10:35:00';
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
            'actual_updated_at'  => $card['actual_updated_at'],
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
        [$syncCards, $data, $user] = $this->getSetup(null, $data, 'google', false);
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
     */
    public function testSaveCardDataNotFound(): void
    {
        $this->partialMock(CardSyncRepository::class, static function ($mock) {
            $mock->shouldReceive('shouldSync')->andReturn(true);
        });
        $this->mock(GenericExtraction::class, static function ($mock) {
            $mock->shouldReceive('setUrl');
            $mock->shouldReceive('getWebsite')->andThrow(new WebsiteNotFound());
        });

        [$syncCards, $data, $user] = $this->getSetup(null, null, 'link');
        $syncCards->syncCards($user);

        $card = Card::find(1);

        $result = $syncCards->saveCardData($card);
        self::assertFalse($result);
        $this->assertDatabaseHas('card_syncs', [
            'card_id' => $card->id,
            'status'  => 2,
        ]);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    public function testSaveCardDataNoSync(): void
    {
        $this->mock(CardSyncRepository::class, static function ($mock) {
            $mock->shouldReceive('shouldSync')->andReturn(false);
        });

        [$syncCards, $data, $user] = $this->getSetup();
        $syncCards->syncCards($user);

        $card = Card::find(1);

        $result = $syncCards->saveCardData($card);
        self::assertFalse($result);
    }

    /**
     * @throws JsonException
     * @throws OauthIntegrationNotFound
     */
    public function testSaveCardData(): void
    {
        Queue::fake();
        $fakeData = collect([
            'content'     => 'cool content',
            'title'       => 'cool title',
            'description' => 'cool description',
            'author'      => 'cool author',
            'image'       => 'https://www.productplan.com/uploads/feature-less-roadmap-1-1024x587.png',
        ]);
        $this->mock(GoogleContent::class, static function ($mock) use ($fakeData) {
            $mock->shouldReceive('getCardContentData')->andReturn(clone $fakeData);
        });

        [$syncCards, $data, $user] = $this->getSetup();
        $syncCards->syncCards($user);

        $cardType = app(CardTypeRepository::class)->firstOrCreate('link');
        $card = Card::find(1);

        // Remove the current image so the new image will save
        $card->card_type_id = $cardType->id;
        $card->image = '';
        $card->save();

        $result = $syncCards->saveCardData($card);

        $this->assertDatabaseHas('cards', [
            'id'                 => '1',
            'content'            => $fakeData->get('content'),
            'title'              => $fakeData->get('title'),
            'description'        => $fakeData->get('description'),
            'properties'         => json_encode(['author' => $fakeData->get('author')], JSON_THROW_ON_ERROR),
        ]);

        $this->assertDatabaseHas('card_syncs', [
            'id'     => 1,
            'status' => 1,
        ]);

        Queue::assertPushed(CardDedupe::class, 1);
        self::assertTrue($result);
    }
}
