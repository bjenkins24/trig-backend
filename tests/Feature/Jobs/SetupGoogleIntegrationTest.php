<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SetupGoogleIntegration;
use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\Card\Integrations\GoogleIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupGoogleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test sync cards job.
     *
     * @return void
     * @group n
     */
    public function testSetup()
    {
        \Queue::fake();
        $this->partialMock(GoogleIntegration::class, function ($mock) {
            $mock->shouldReceive('syncDomains')->once();
        });

        (new SetupGoogleIntegration(User::find(1)))->handle();

        \Queue::assertPushed(SyncCards::class, 1);
    }
}
