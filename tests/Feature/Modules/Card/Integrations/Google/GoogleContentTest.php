<?php

namespace Tests\Feature\Modules\Card\Integrations\Google;

use App\Models\Card;
use App\Modules\Card\Exceptions\OauthUnauthorizedRequest;
use App\Modules\Card\Integrations\Google\GoogleConnection;
use App\Modules\Card\Integrations\Google\GoogleContent;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use Tests\Feature\Modules\Card\Integrations\Google\Fakes\FakeGoogleServiceDrive;
use Tests\Feature\Modules\Card\Integrations\Google\Fakes\FakeGoogleServiceDriveFiles;
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

    /**
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function testGetCardContentGoogle(): void
    {
        $this->mock(GoogleConnection::class, static function ($mock) {
            $mock->shouldReceive('getDriveService')->andReturn(new FakeGoogleServiceDrive());
        });
        $content = app(GoogleContent::class)->getCardContent(Card::find(1), 1, 'application/vnd.google-apps.document');
        self::assertEquals(FakeGoogleServiceDriveFiles::EXPORTED, $content);
    }

    /**
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function testGetCardContentNotGoogle(): void
    {
        $this->mock(GoogleConnection::class, static function ($mock) {
            $mock->shouldReceive('getDriveService')->andReturn(new FakeGoogleServiceDrive());
        });
        $content = app(GoogleContent::class)->getCardContent(Card::find(1), 1, 'text/plain');
        self::assertEquals(FakeGoogleServiceDriveFiles::GET, $content);
    }

    /**
     * @throws OauthUnauthorizedRequest
     * @throws OauthIntegrationNotFound
     */
    public function testGetCardGoogleNoMime(): void
    {
        $this->mock(GoogleConnection::class, static function ($mock) {
            $mock->shouldReceive('getDriveService')->andReturn(new FakeGoogleServiceDrive());
        });
        $content = app(GoogleContent::class)->getCardContent(Card::find(1), 1, 'application/vnd.google-apps.fakemime');
        self::assertEmpty($content);
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
