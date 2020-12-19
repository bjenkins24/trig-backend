<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SetupGoogleIntegration;
use App\Jobs\SyncCards;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Card\Integrations\Google\GoogleDomains;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SetupGoogleIntegrationTest extends TestCase
{
    /**
     * Test sync cards job.
     */
    public function testSetup(): void
    {
        Queue::fake();
        $this->partialMock(GoogleDomains::class, static function ($mock) {
            $mock->shouldReceive('syncDomains')->once();
        });

        $organization = Organization::find(1);
        (new SetupGoogleIntegration(User::find(1), $organization))->handle();

        Queue::assertPushed(SyncCards::class, 1);
    }
}
