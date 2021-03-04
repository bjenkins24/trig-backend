<?php

namespace Tests\Feature\Modules\Card\Integrations\Link;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Integrations\Link\LinkIntegration;
use App\Utils\ExtractDataHelper;
use Tests\TestCase;

class LinkIntegrationTest extends TestCase
{
    public function testGetKey(): void
    {
        $this->mock(ExtractDataHelper::class);
        $integrationKey = app(LinkIntegration::class)->getIntegrationKey();
        self::assertEquals('link', $integrationKey);
    }

    public function testGetAllCardData(): void
    {
        $this->mock(ExtractDataHelper::class);
        self::assertEquals([], app(LinkIntegration::class)->getAllCardData(User::find(1), Workspace::find(1), 123));
    }
}
