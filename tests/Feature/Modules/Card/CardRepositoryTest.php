<?php

namespace Tests\Feature\Modules\Card;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use App\Modules\Card\Exceptions\CardIntegrationCreationValidate;
use App\Modules\OauthIntegration\OauthIntegrationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardRepositoryTest extends TestCase
{
    use RefreshDatabase;

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
}
