<?php

namespace Tests\Feature\Modules\OauthIntegration;

use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Tests\TestCase;

class OauthIntegrationServiceTest extends TestCase
{
    /**
     * Get access token.
     *
     * @return void
     */
    public function testIncorrectIntegrationKey()
    {
        $this->expectException(OauthIntegrationNotFound::class);
        app(OauthIntegrationService::class)->makeIntegration('find it', 'google', 'integration');
    }
}
