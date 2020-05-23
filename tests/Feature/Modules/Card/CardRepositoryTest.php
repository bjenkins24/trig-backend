<?php

namespace Tests\Feature\Modules\Card;

use App\Models\Card;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use App\Modules\Permission\PermissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardRepositoryTest extends TestCase
{
    use RefreshDatabase;

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
        $id = 1;
        $result->each(function ($card) use (&$id) {
            $this->assertEquals($card->id, $id);
            ++$id;
        });
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
    }

    public function testDenormalizePermissionsNoPermissions()
    {
        $card = Card::find(1);

        $permissions = app(CardRepository::class)->denormalizePermissions($card)->toArray();
        $this->assertEquals($permissions, []);
    }
}
