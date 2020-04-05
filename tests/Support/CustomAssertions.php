<?php

namespace Tests\Support;

use Illuminate\Support\Arr;

trait CustomAssertions
{
    public function assertLoggedInResponse($response, $email)
    {
        $this->assertTrue(Arr::has($response->json(), 'data.auth_token.access_token'));
        $this->assertTrue(
            Arr::get($response->json(), 'data.user.email') === $email
        );
    }
}
