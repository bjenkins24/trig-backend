<?php

namespace Tests\Feature\Modules\Card\Integrations\Link;

use App\Models\User;
use App\Modules\Card\Integrations\Link\LinkIntegration;
use Tests\TestCase;

class LinkIntegrationTest extends TestCase
{
    public function testGetKey(): void
    {
        $integrationKey = app(LinkIntegration::class)->getIntegrationKey();
        self::assertEquals('link', $integrationKey);
    }

    public function testGetAllCardData(): void
    {
        self::assertEquals([], app(LinkIntegration::class)->getAllCardData(User::find(1), 123));
    }
}
