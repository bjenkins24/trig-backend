<?php

namespace Tests\Feature\Modules\Card\Integrations\Google;

use App\Modules\Card\Integrations\Google\GoogleContent;
use Tests\Support\Traits\SyncCardsTrait;
use Tests\TestCase;

class GoogleContentTest extends TestCase
{
    use SyncCardsTrait;

    /**
     * @dataProvider googleToMimeProvider
     */
    public function testGoogleToMime(string $mime, string $expected): void
    {
        self::assertEquals($expected, app(GoogleContent::class)->googleToMime($mime));
    }

    public function googleToMimeProvider(): array
    {
        return [
            ['application/vnd.google-apps.audio', ''],
            ['application/vnd.google-apps.document', 'text/plain'],
            ['application/vnd.google-apps.spreadsheet', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }
}
