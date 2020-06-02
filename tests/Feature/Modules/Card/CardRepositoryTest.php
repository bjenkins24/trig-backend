<?php

namespace Tests\Feature\Modules\Card;

use App\Models\Card;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use App\Modules\Permission\PermissionRepository;
use Carbon\Carbon;
use Tests\TestCase;

class CardRepositoryTest extends TestCase
{
    const MOCK_SEARCH_RESPONSE = [
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
                    '_index' => 'card',
                    '_type'  => 'cards',
                    '_id'    => '1',
                    '_score' => 1.0,
                ],
                [
                    '_index' => 'card',
                    '_type'  => 'cards',
                    '_id'    => '2',
                    '_score' => 1.0,
                ],
            ],
        ],
    ];

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testFailCreateIntegration()
    {
        $this->expectException(CardIntegrationCreationValidate::class);
        $this->partialMock(OauthIntegrationRepository::class, function ($mock) {
            $mock->shouldReceive('findByName')->andReturn(null)->once();
        });

        app(CardRepository::class)->createIntegration(Card::find(1), 123, 'google');
    }

    /**
     * Test if search for cards returns card objects.
     *
     * @return void
     */
    public function testSearchCards()
    {
        $this->partialMock(CardRepository::class, function ($mock) {
            $mock->shouldReceive('searchCardsRaw')->andReturn(self::MOCK_SEARCH_RESPONSE)->once();
        });
        $result = app(CardRepository::class)->searchCards(User::find(1));
        $hasOne = false;
        $hasTwo = false;
        $result->each(function ($card) use (&$hasOne, &$hasTwo) {
            if (1 === $card->id) {
                $hasOne = true;
            }
            if (2 === $card->id) {
                $hasTwo = true;
            }
        });
        $this->assertTrue($hasOne);
        $this->assertTrue($hasTwo);
    }

    public function testDenormalizePermissions()
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
        $this->assertEquals([
            ['type' => 'App\Models\User', 'id' => 1],
            ['type' => 'App\Models\Person', 'id' => 1],
            ['type' => null, 'id' => null],
        ], $permissions);
        $this->refreshDb();
    }

    public function testDenormalizePermissionsNoPermissions()
    {
        $card = Card::find(1);

        $permissions = app(CardRepository::class)->denormalizePermissions($card)->toArray();
        $this->assertEquals($permissions, []);
    }

    public function testGetOrganization()
    {
        $organization = app(CardRepository::class)->getOrganization(Card::find(1));
        $this->assertEquals(1, $organization->id);
    }

    public function testDedupe()
    {
        $card1 = Card::find(1);
        $card1->actual_modified_at = Carbon::now()->subDays(20);
        $card1->save();

        // Card 2 is more recent so it should become the primary_card_id
        $card2 = Card::find(2);
        $card2->actual_modified_at = Carbon::now()->subDays(10);
        $card2->save();

        $card3 = Card::find(3);
        $card3->actual_modified_at = Carbon::now()->subDays(30);
        $card3->save();

        $this->partialMock(CardRepository::class, function ($mock) {
            $mock->shouldReceive('getDuplicates')->andReturn(collect([2, 3]));
        });

        $result = app(CardRepository::class)->dedupe($card1);
        $this->assertTrue($result);

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

        $card3->actual_modified_at = Carbon::now()->subDays(1);
        $card3->save();
        $result = app(CardRepository::class)->dedupe($card1);
        $this->assertTrue($result);

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

    public function testDedupeNoContent()
    {
        $card = Card::find(1);
        $card->content = '';
        $card->save();
        $result = app(CardRepository::class)->dedupe($card);

        $this->assertFalse($result);

        // Revert save so we don't have to rescaffold tests
        $card->content = 'fake content here';
        $card->save();
    }

    public function testDedupeNoDuplicates()
    {
        $card = Card::find(1);
        $this->partialMock(CardRepository::class, function ($mock) {
            $mock->shouldReceive('getDuplicates')->andReturn(collect([]));
        });

        $result = app(CardRepository::class)->dedupe($card);
        $this->assertFalse($result);
    }

    public function testGetDuplicates()
    {
        $card = Card::find(1);
        \Http::fake();
        $result = app(CardRepository::class)->getDuplicates($card);
        \Http::assertSent(function ($request) use ($card) {
            return
                'http://localhost:5000/dedupe' == $request->url() &&
                $request['id'] == $card->id &&
                $request['content'] == $card->content &&
                $request['organization_id'] == app(CardRepository::class)->getOrganization($card)->id;
        });
    }

    public function testGetDuplicatesForbidden()
    {
        $card = Card::find(1);
        \Http::fake([
            '*' => \Http::response('Hello World', 403, ['Headers']),
        ]);
        $result = app(CardRepository::class)->getDuplicates($card);
        $this->assertEquals(collect([]), $result);
    }
}
