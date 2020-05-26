<?php

namespace Tests\Feature\Modules\OauthConnection;

use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use App\Modules\OauthConnection\OauthConnectionRepository;
use Tests\TestCase;

class OauthConnectionRepositoryTest extends TestCase
{
    /**
     * Get access token.
     *
     * @return void
     */
    public function testMissingTokens()
    {
        $this->expectException(OauthMissingTokens::class);
        $accessToken = app(OauthConnectionRepository::class)->create(User::find(1), 'google', collect([]));
    }
}
