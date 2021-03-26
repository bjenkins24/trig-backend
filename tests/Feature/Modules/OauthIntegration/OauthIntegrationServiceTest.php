<?php

namespace Tests\Feature\Modules\OauthIntegration;

use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OauthIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get access token.
     */
    public function testIncorrectIntegrationKey(): void
    {
        $this->expectException(OauthIntegrationNotFound::class);
        app(OauthIntegrationService::class)->makeIntegration('find it', 'google', 'integration');
    }

    /**
     * @dataProvider integrationProvider
     */
    public function testIsIntegrationValid(string $key, bool $expectedResult): void
    {
        $isValid = app(OauthIntegrationService::class)->isIntegrationValid($key);
        self::assertEquals($expectedResult, $isValid);
    }

    public function integrationProvider(): array
    {
        return [
            ['fakeKey', false],
            ['google',  true],
            ['link',    true],
        ];
    }
}
