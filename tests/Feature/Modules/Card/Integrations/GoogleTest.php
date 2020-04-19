<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Models\User;
use App\Modules\Card\Integrations\GoogleIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Card\Integrations\Fakes\FileFake;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    use RefreshDatabase;
    use CreateOauthConnection;

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncCards()
    {
        $user = User::find(1);
        $this->createOauthConnection($user);
        $fakeTitle = "Brian's Title";
        $fakeThumbnailUrl = '/storage/public/card-thumbnails/1.jpg';
        $fakeUrl = 'http://myfakeurl.example.com';
        $fakeId = 'My fake Id';
        $this->partialMock(GoogleIntegration::class, function ($mock) use ($fakeTitle, $fakeUrl, $fakeId) {
            $file = new FileFake();
            $file->name = $fakeTitle;
            $file->webViewLink = $fakeUrl;
            $file->id = $fakeId;

            $mock->shouldReceive('getFiles')->andReturn(collect([new FileFake(), $file]))->once();
            $mock->shouldReceive('getThumbnail')
                ->andReturn(collect(['thumbnail' => 'content', 'extension' => 'jpeg']))
                ->twice();
        });

        \Storage::shouldReceive('put')->andReturn(true)->twice();
        \Storage::shouldReceive('url')->andReturn($fakeThumbnailUrl)->twice();

        $result = app(GoogleIntegration::class)->syncCards($user);

        $this->assertDatabaseHas('cards', [
            'title' => $fakeTitle,
            'image' => \Config::get('app.url').$fakeThumbnailUrl,
        ]);

        $this->assertDatabaseHas('card_links', [
            'link' => $fakeUrl,
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'foreign_id' => $fakeId,
        ]);
    }
}
