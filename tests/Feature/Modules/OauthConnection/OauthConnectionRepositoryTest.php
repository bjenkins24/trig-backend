<?php

namespace Tests\Feature\Modules\User;

use App\Models\User;
use App\Modules\OauthConnection\Exceptions\OauthMissingTokens;
use App\Modules\OauthConnection\OauthConnectionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OauthConnectionRepositoryTest extends TestCase
{
    use RefreshDatabase;

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
