<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Nova;
use Laravel\Nova\Tests\IntegrationTest;

class AuthorizationTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testAuthorizationCallbackIsExecuted()
    {
        Nova::auth(function ($request) {
            return $request;
        });

        $this->assertEquals('Taylor', Nova::check('Taylor'));
    }
}
