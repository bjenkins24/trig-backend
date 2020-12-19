<?php

namespace Tests\Feature\Modules\Card;

use App\Models\Card;
use App\Models\CardDuplicate;
use App\Models\Permission;
use App\Models\Person;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardExists;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\Card\Exceptions\CardOrganizationIdMustExist;
use App\Modules\Card\Exceptions\OauthKeyInvalid;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use App\Modules\Permission\PermissionRepository;
use Carbon\Carbon;
use Exception;
use Tests\TestCase;

class CardRepositoryTest extends TestCase
{
    public const MOCK_SEARCH_RESPONSE = [
        'took'      => 1,
        'timed_out' => false,
        '_shards'   => [
            'total'      => 1,
            'successful' => 1,
            'skipped'    => 0,
            'failed'     => 0,
        ],
        'hits' => [
            'total' => [
                'value'    => 4026,
                'relation' => 'eq',
            ],
            'max_score' => 1.0,
            'hits'      => [
                [
                    '_index'  => 'card',
                    '_type'   => 'cards',
                    '_id'     => '2',
                    '_score'  => 1.0,
                    '_source' => [
                        'user_id'           => 7,
                        'card_type_id'      => 2,
                        'organization_id'   => 3,
                        'title'             => 'Interview with a Engineer',
                        'doc_title'         => null,
                        'content'           => '',
                        'permissions'       => [],
                        'actual_created_at' => '2020-11-13T22:19:36.000000Z',
                        'card_duplicate_ids'=> '10',
                    ],
                    'fields' => [
                        'card_duplicate_ids' => ['11'],
                    ],
                    'highlight' => [
                        'title' => [
                            '<em>Interview<\/em> with a Engineer',
                        ],
                        'content' => [
                            '<em>Cool<\/em> content with engineer',
                        ],
                    ],
                ],
                [
                    '_index'  => 'card',
                    '_type'   => 'cards',
                    '_id'     => '1',
                    '_score'  => 0.7,
                    '_source' => [
                        'user_id'           => 7,
                        'card_type_id'      => 1,
                        'organization_id'   => 2,
                        'title'             => 'Interview with a Product Manager from FANG',
                        'doc_title'         => null,
                        'content'           => "INTERVIEW WITH A PRODUCT MANAGER FROM FANG\n\n [https:\/\/www.youtube.com\/channel\/UCfmqLyr1PI3_zbwppHNEzuQ]\n\nHow Compelling Is Your Writing?\n\nGrammarly [\/channel\/UCfmqLyr1PI3_zbwppHNEzuQ]\n\n",
                        'permissions'       => [],
                        'actual_created_at' => '2020-11-13T22:19:36.000000Z',
                        'card_duplicate_ids'=> '10',
                    ],
                    'fields' => [
                        'card_duplicate_ids' => ['10'],
                    ],
                    'highlight' => [
                        'title' => [
                            '<em>Interview<\/em> with a Product Manager from FANG',
                        ],
                        'content' => [
                            '<em>INTERVIEW<\/em> WITH A PRODUCT MANAGER FROM FANG\n\n [https:\/\/www.youtube.com\/channel\/UCfmqLyr1PI3_zbwppHNEzuQ]\n\nHow Compelling Is Your Writing?\n\nGrammarly [\/channel\/UCfmqLyr1PI3_zbwppHNEzuQ]',
                        ],
                    ],
                ],
                [
                    '_index'  => 'card',
                    '_type'   => 'cards',
                    '_id'     => '29084129',
                    '_score'  => 0.5,
                    '_source' => [
                        'user_id'           => 7,
                        'card_type_id'      => 2,
                        'organization_id'   => 3,
                        'title'             => "This one isn't in the db",
                        'doc_title'         => null,
                        'content'           => '',
                        'permissions'       => [],
                        'actual_created_at' => '2020-11-13T22:19:36.000000Z',
                        'card_duplicate_ids'=> '10',
                    ],
                    'fields' => [
                        'card_duplicate_ids' => ['11'],
                    ],
                    'highlight' => [
                        'title' => [
                            '<em>Interview<\/em> with a Engineer',
                        ],
                    ],
                ],
            ],
        ],
    ];

    /**
     * Test syncing all integrations.
     */
    public function testFailCreateIntegration(): void
    {
        $this->expectException(CardIntegrationCreationValidate::class);
        $this->partialMock(OauthIntegrationRepository::class, static function ($mock) {
            $mock->shouldReceive('findByName')->andReturn(null)->once();
        });

        app(CardRepository::class)->createIntegration(Card::find(1), 123, 'google');
    }

    /**
     * Test if search for cards returns card objects.
     */
    public function testSearchCards(): void
    {
        $this->partialMock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('searchCardsRaw')->andReturn(self::MOCK_SEARCH_RESPONSE)->once();
        });
        $result = app(CardRepository::class)->searchCards(User::find(1), collect(['q' => 'test']));

        $fields = collect([
            'id',
            'token',
            'cardType',
            'title',
            'url',
            'image',
            'imageWidth',
            'imageHeight',
            'totalFavorites',
            'isFavorited',
            'lastAttemptedSync',
            'createdAt',
        ]);

        $userFields = collect([
            'id',
            'email',
            'firstName',
            'lastName',
        ]);

        self::assertEquals([
            'totalPages'   => (int) ceil(4026 / CardRepository::DEFAULT_SEARCH_LIMIT),
            'page'         => 0,
            // This is one less than above because one of the results is in elastic search, but not in the DB
            // So we change the total results
            'totalResults' => 4026 - 1,
        ], $result->get('meta'));

        // Reverse the order - sort by score check
        self::assertEquals(2, $result->get('cards')[0]['id']);
        self::assertEquals(1, $result->get('cards')[1]['id']);

        // The last hit shouldn't exist because it's an ID that doesn't exist in the DB
        self::assertEmpty($result->get(2));

        $data = $result->get('cards')[0];
        $fields->each(static function ($field) use ($data) {
            self::assertArrayHasKey($field, $data);
        });
        self::assertArrayNotHasKey('highlights', $data);

        $user = $data['user'];
        $userFields->each(static function ($field) use ($user) {
            self::assertArrayHasKey($field, $user);
        });

        $hasOne = false;
        $hasTwo = false;
        $result->get('cards')->each(static function ($card) use (&$hasOne, &$hasTwo) {
            if (1 === $card['id']) {
                $hasOne = true;
            }
            if (2 === $card['id']) {
                $hasTwo = true;
            }
        });
        self::assertTrue($hasOne);
        self::assertTrue($hasTwo);
    }

    public function testSearchCardsHighlights(): void
    {
        $this->partialMock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('searchCardsRaw')->andReturn(self::MOCK_SEARCH_RESPONSE)->once();
        });
        $result = app(CardRepository::class)->searchCards(User::find(1), collect(['h' => '1']));
        $card = $result->get('cards')[0];
        self::assertArrayHasKey('highlights', $card);
        self::assertArrayHasKey('title', $card['highlights']);
        self::assertArrayHasKey('content', $card['highlights']);
    }

    public function testDenormalizePermissions(): void
    {
        $card = Card::find(1);

        $permissionRepo = app(PermissionRepository::class);
        // User Permission
        $permissionRepo->createEmail($card, 'writer', User::find(1)->email);
        // Person Permission
        $permissionRepo->createEmail($card, 'writer', 'testEmail@example.com');
        // Anyone Permission
        $permissionRepo->createAnyone($card, 'writer');

        $permissions = app(CardRepository::class)->denormalizePermissions($card)->toArray();
        self::assertEquals([
            ['type' => User::class, 'id' => 1],
            ['type' => Person::class, 'id' => 1],
            ['type' => null, 'id' => null],
        ], $permissions);
        $this->refreshDb();
    }

    public function testDenormalizePermissionsNoPermissions(): void
    {
        $card = Card::find(1);

        $permissions = app(CardRepository::class)->denormalizePermissions($card)->toArray();
        self::assertEquals([], $permissions);
    }

    public function testGetOrganization(): void
    {
        $organization = app(CardRepository::class)->getOrganization(Card::find(1));
        self::assertEquals(2, $organization->id);
    }

    public function testGetCardIntegration(): void
    {
        $cardIntegration = app(CardRepository::class)->getCardIntegration(Card::find(1));
        self::assertEquals(1, $cardIntegration->id);
    }

    public function testGetCardType(): void
    {
        $cardType = app(CardRepository::class)->getCardType(Card::find(1));
        self::assertEquals(3, $cardType->id);
    }

    public function testGetUser(): void
    {
        $user = app(CardRepository::class)->getUser(Card::find(1));
        self::assertEquals(1, $user->id);
    }

    public function testDedupe(): void
    {
        $card1 = Card::find(1);
        $card1->actual_updated_at = Carbon::now()->subDays(20);
        $card1->save();

        // Card 2 is more recent so it should become the primary_card_id
        $card2 = Card::find(2);
        $card2->actual_updated_at = Carbon::now()->subDays(10);
        $card2->save();

        $card3 = Card::find(3);
        $card3->actual_updated_at = Carbon::now()->subDays(30);
        $card3->save();

        $this->partialMock(CardRepository::class, function ($mock) {
            $mock->shouldReceive('getDuplicates')->andReturn(collect([2, 3]));
        });

        $result = app(CardRepository::class)->dedupe($card1);
        self::assertTrue($result);

        $this->assertDatabaseHas('card_duplicates', [
            'primary_card_id'   => 2,
            'duplicate_card_id' => 1,
        ]);
        $this->assertDatabaseHas('card_duplicates', [
            'primary_card_id'   => 2,
            'duplicate_card_id' => 3,
        ]);
        $this->assertDatabaseMissing('card_duplicates', [
            'primary_card_id'   => 2,
            'duplicate_card_id' => 2,
        ]);

        $card3->actual_updated_at = Carbon::now()->subDays(1);
        $card3->save();
        $result = app(CardRepository::class)->dedupe($card1);
        self::assertTrue($result);

        $this->assertDatabaseHas('card_duplicates', [
            'primary_card_id'   => 3,
            'duplicate_card_id' => 1,
        ]);
        $this->assertDatabaseHas('card_duplicates', [
            'primary_card_id'   => 3,
            'duplicate_card_id' => 2,
        ]);
        $this->refreshDb();
    }

    public function testDedupeNoContent(): void
    {
        $card = Card::find(1);
        $card->content = '';
        $card->save();
        $result = app(CardRepository::class)->dedupe($card);

        self::assertFalse($result);

        // Revert save so we don't have to rescaffold tests
        $card->content = 'fake content here';
        $card->save();
    }

    public function testDedupeNoDuplicates(): void
    {
        $card = Card::find(1);
        $this->partialMock(CardRepository::class, static function ($mock) {
            $mock->shouldReceive('getDuplicates')->andReturn(collect([]));
        });

        $result = app(CardRepository::class)->dedupe($card);
        self::assertFalse($result);
    }

    public function testGetDuplicates(): void
    {
        $card = Card::find(1);
        \Http::fake();
        $result = app(CardRepository::class)->getDuplicates($card);
        \Http::assertSent(static function ($request) use ($card) {
            return
                'http://localhost:5000/dedupe' === $request->url() &&
                $request['id'] == $card->id &&
                $request['content'] == $card->content &&
                $request['organization_id'] == app(CardRepository::class)->getOrganization($card)->id;
        });
    }

    public function testGetDuplicatesForbidden(): void
    {
        $card = Card::find(1);
        \Http::fake([
            '*' => \Http::response('Hello World', 403, ['Headers']),
        ]);
        $result = app(CardRepository::class)->getDuplicates($card);
        self::assertEquals(collect([]), $result);
    }

    public function testGetDuplicateIdsNoDuplicates(): void
    {
        $card = Card::find(1);
        $result = app(CardRepository::class)->getDuplicateIds($card);
        self::assertEquals('1', $result);
    }

    public function testGetDuplicateIds(): void
    {
        $card = Card::find(2);
        CardDuplicate::create([
            'primary_card_id'   => 2,
            'duplicate_card_id' => 4,
        ]);
        CardDuplicate::create([
            'primary_card_id'   => 2,
            'duplicate_card_id' => 3,
        ]);
        $result = app(CardRepository::class)->getDuplicateIds($card);
        self::assertEquals('2_3_4', $result);
    }

    /**
     * @throws OauthKeyInvalid
     */
    public function testGetByForeignId(): void
    {
        $cardId = 1;
        $foreignId = Card::find($cardId)->cardIntegration()->first()->foreign_id;
        $card = app(CardRepository::class)->getByForeignId($foreignId, 'google');
        self::assertEquals($card->id, $cardId);

        $card = app(CardRepository::class)->getByForeignId('no way is this real', 'google');
        self::assertNull($card);
    }

    public function testNoActualCreatedUpdateOrInsert(): void
    {
        $this->refreshDb();
        $knownDate = Carbon::create(2001, 5, 21, 12);
        Carbon::setTestNow($knownDate);

        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail');
        });
        $title = 'mycooltitle';
        app(CardRepository::class)->updateOrInsert([
            'image'        => 'cool_image',
            'title'        => $title,
            'url'          => 'https://coolurl',
            'card_type_id' => 2,
            'user_id'      => 1,
        ]);

        $this->assertDatabaseHas('cards', [
            'title'              => $title,
            'actual_created_at'  => $knownDate,
            'actual_updated_at'  => $knownDate,
        ]);
    }

    /**
     * @throws Exception
     * @dataProvider urlProvider
     */
    public function testCardExists(string $testUrl, bool $expectedExist): void
    {
        $url = 'https://www.mycooltest.com';
        $this->refreshDb();
        app(CardRepository::class)->updateOrInsert([
            'url'          => $url,
            'title'        => 'my cool test',
            'user_id'      => 1,
            'card_type_id' => 1,
        ]);
        $exists = app(CardRepository::class)->cardExists($testUrl, 1);
        self::assertEquals($exists, $expectedExist);
        $exists = app(CardRepository::class)->cardExists($url, 2);
        self::assertFalse($exists);
    }

    public function urlProvider(): array
    {
        return [
            ['https://www.mycooltest.com', true],
            ['https://mycooltest.com', true],
            ['http://www.mycooltest.com', true],
            ['http://mycooltest.com', true],
            ['www.mycooltest.com', true],
            ['mycooltest.com', true],
            ['mycooltest', false],
            // We're allowing duplicate urls with a hash because some JS routers use a hash instead of a normal url to route to different internal pages
            ['mycooltest.com#mytag', false],
            ['mycooltest.net', false],
        ];
    }

    /**
     * @throws Exception
     */
    public function testUpdateOrInsert(): void
    {
        $this->refreshDb();
        $card = Card::find(1);
        $title = 'my cool title';
        $this->mock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail');
        });
        $firstCardUrl = 'https://firstCardUrl.com';
        $favoritedById = 1;
        $viewedById = 1;
        app(CardRepository::class)->updateOrInsert([
            'url'         => $firstCardUrl,
            'title'       => $title,
            'image'       => 'cool_image',
            'favoritedBy' => $favoritedById,
            'viewedBy'    => $viewedById,
        ], $card);
        $this->assertDatabaseHas('cards', [
            'id'               => 1,
            'title'            => $title,
            'total_favorites'  => 1,
            'total_views'      => 1,
        ]);

        $this->assertDatabaseMissing('card_syncs', [
            'card_id' => 1,
            'status'  => 1,
        ]);

        $this->assertDatabaseHas('card_favorites', [
            'card_id' => 1,
            'user_id' => $favoritedById,
        ]);

        $this->assertDatabaseHas('card_views', [
            'card_id' => 1,
            'user_id' => $viewedById,
        ]);

        app(CardRepository::class)->updateOrInsert([
            'title'         => $title,
            'image'         => 'cool_image',
            'unfavoritedBy' => $favoritedById,
        ], $card);

        $this->assertDatabaseHas('cards', [
            'id'              => 1,
            'total_favorites' => 0,
        ]);

        $this->assertDatabaseMissing('card_favorites', [
            'card_id' => 1,
            'user_id' => $favoritedById,
        ]);

        $newCardTitle = 'my new card';
        $newCard = app(CardRepository::class)->updateOrInsert([
            'title'              => $newCardTitle,
            'user_id'            => 1,
            'card_type_id'       => 2,
            'url'                => 'https://www.foodnetwork.com/recipes/ina-garten/perfect-roast-turkey-recipe4-1943576#:~:text=turkey,-%20and%20wash%20the%20turkey',
            'actual_created_at'  => '123',
            'actual_updated_at'  => '123',
            'favorited'          => false,
        ], null);
        self::assertNotEmpty($newCard->token);
        $this->assertDatabaseMissing('card_favorites', [
            'card_id' => 6,
            'user_id' => 1,
        ]);

        // Try an existing card with a url that already exists
        try {
            app(CardRepository::class)->updateOrInsert([
                'user_id'            => 1,
                'url'                => $firstCardUrl,
            ], $newCard);
            // This should never be reached
            self::assertFalse(true);
        } catch (CardExists $exception) {
            self::assertTrue(true);
        }

        // Try a new card with a url that already exists - it should throw an error
        try {
            app(CardRepository::class)->updateOrInsert([
                'user_id' => 1,
                'url'     => 'foodnetwork.com/recipes/ina-garten/perfect-roast-turkey-recipe4-1943576',
                'title'   => $newCardTitle,
            ], null);
            self::assertFalse(true);
        } catch (CardExists $exception) {
            self::assertTrue(true);
        }

        $this->assertDatabaseHas('cards', [
            'title'        => $newCardTitle,
            'url'          => 'https://www.foodnetwork.com/recipes/ina-garten/perfect-roast-turkey-recipe4-1943576',
            'card_type_id' => 2,
        ]);
        self::assertEquals($newCard->title, $newCardTitle);

        // Try a user with more than one organization, it should throw an error
        User::find(1)->organizations()->create([
            'name' => 'test org',
        ]);
        try {
            app(CardRepository::class)->updateOrInsert([
                'user_id' => 1,
                'url'     => 'foodnetwork.com/recipes/ina-garten/perfect-roast-turkey-recipe4-1943576',
                'title'   => $newCardTitle,
            ], null);
            self::assertFalse(true);
        } catch (CardOrganizationIdMustExist $exception) {
            self::assertTrue(true);
        }

        $this->refreshDb();
    }

    public function testRemovePermissions(): void
    {
        $fields = [
            'permissionable_type' => Card::class,
            'permissionable_id'   => 1,
            'capability_id'       => 1,
        ];
        Permission::create($fields);
        app(CardRepository::class)->removeAllPermissions(Card::find(1));
        $this->assertDatabaseMissing('permissions', $fields);
    }
}
