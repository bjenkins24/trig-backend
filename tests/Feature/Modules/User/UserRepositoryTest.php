<?php

namespace Tests\Feature\Modules\User;

use App\Models\OauthConnection;
use App\Models\OauthIntegration;
use App\Models\User;
use App\Modules\User\UserRepository;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    public function testGetAllActiveIntegrations()
    {
        $confluenceId = OauthIntegration::create([
            'name' => 'confluence',
        ])->id;
        OauthConnection::create([
            'user_id'              => 1,
            'oauth_integration_id' => 1,
            'access_token'         => '123',
            'refresh_token'        => '123',
            'expires'              => '2020-06-04 06:29:39',
        ]);
        OauthConnection::create([
            'user_id'              => 1,
            'oauth_integration_id' => $confluenceId,
            'access_token'         => '123',
            'refresh_token'        => '123',
            'expires'              => '2020-06-04 06:29:39',
        ]);
        $result = app(UserRepository::class)->getAllActiveIntegrations(User::find(1));
        $this->assertEquals(collect(['google', 'confluence']), $result);
        $this->refreshDb();
    }
}
