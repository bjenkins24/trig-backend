<?php

namespace Tests\Feature\Models;

use App\Models\User;
use App\Modules\Card\CardService;
use App\Modules\Card\Integrations\Google;
use App\Modules\OauthConnection\OauthConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create fake oauth connection for testing.
     *
     * @return void
     */
    private function createOauthConnection()
    {
        $user = User::find(1);

        app(OauthConnectionService::class)->storeConnection(
            $user,
            'google',
            collect([
                'access_token'  => 'ya29.a0Ae4lvC1wU4oWWGgTXbw79vJVtjstCV1Hy2Di-dmYApdjQOQomWfg4w9OZpManqJvxD1VXwEiAAvxo_fQIQwb6fumSKKiO-ViYEHaJTsxWS8uXyHIoB_d6vLGL-IxAf9tW8VFWQCHeP3Im17PU029ZtDna3ssBK12y-w',
                'refresh_token' => '1//0fhpEZ1LyYyXNCgYIARAAGA8SNwF-L9IrF4-hXziVN01TUS0Gb33Xdr5o6iFpS_rtJ6c1eEQiHmnov3vKfZIinJX2_pAXoZGvm70',
                'expires_in'    => 3600,
            ])
        );

        return $user;
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncAll()
    {
        $user = $this->createOauthConnection();
        // $this->partialMock(OauthConnectionService::class, function ($mock) {
        //     $mock->shouldReceive('getClient')->once();
        // });

        $this->mock(GoogleServiceDrive::class, function ($mock) {
        });

        $this->mock(Google::class, function ($mock) {
            $mock->shouldReceive('syncCards')->once();
        });

        app(CardService::class)->syncAllIntegrations($user);
    }
}
