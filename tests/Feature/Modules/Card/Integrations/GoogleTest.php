<?php

namespace Tests\Feature\Modules\Card\Integrations;

use App\Models\User;
use App\Modules\Card\Integrations\Google;
use App\Modules\OauthConnection\OauthConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create fake oauth connection for testing.
     *
     * @return void
     */
    private function createOauthConnection()
    {
        $user = User::find(1);

        app(OauthConnectionService::class)->storeConnection(
            $user,
            'google',
            collect([
                'access_token'  => 'ya29.a0Ae4lvC1wU4oWWGgTXbw79vJVtjstCV1Hy2Di-dmYApdjQOQomWfg4w9OZpManqJvxD1VXwEiAAvxo_fQIQwb6fumSKKiO-ViYEHaJTsxWS8uXyHIoB_d6vLGL-IxAf9tW8VFWQCHeP3Im17PU029ZtDna3ssBK12y-w',
                'refresh_token' => '1//0fhpEZ1LyYyXNCgYIARAAGA8SNwF-L9IrF4-hXziVN01TUS0Gb33Xdr5o6iFpS_rtJ6c1eEQiHmnov3vKfZIinJX2_pAXoZGvm70',
                'expires_in'    => 3600,
            ])
        );

        return $user;
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncCards()
    {
        $user = $this->createOauthConnection();
        $fakeTitle = "Brian's Title";
        $fakeThumbnailUrl = '/storage/public/card-thumbnails/1.jpg';
        $fakeUrl = 'http://myfakeurl.example.com';
        $fakeId = 'My fake Id';
        $this->partialMock(Google::class, function ($mock) use ($fakeTitle, $fakeUrl, $fakeId) {
            $file = new FakeFiles();
            $file->name = $fakeTitle;
            $file->webViewLink = $fakeUrl;
            $file->id = $fakeId;

            $mock->shouldReceive('getFiles')->andReturn(collect([new FakeFiles(), $file]))->once();
            $mock->shouldReceive('getThumbnail')
                ->andReturn(collect(['thumbnail' => 'content', 'extension' => 'jpeg']))
                ->twice();
        });

        Storage::shouldReceive('put')->andReturn(true)->twice();
        Storage::shouldReceive('url')->andReturn($fakeThumbnailUrl)->twice();

        $result = app(Google::class)->syncCards($user);

        $this->assertDatabaseHas('cards', [
            'title' => $fakeTitle,
            'image' => Config::get('app.url').$fakeThumbnailUrl,
        ]);

        $this->assertDatabaseHas('card_links', [
            'link' => $fakeUrl,
        ]);

        $this->assertDatabaseHas('card_integrations', [
            'foreign_id' => $fakeId,
        ]);
    }
}

class FakeFiles
{
    public $appProperties = null;
    public $copyRequiresWriterPermission = null;
    public $createdTime = '2018-12-20T23:08:20.325Z';
    public $description = null;
    public $driveId = null;
    public $explicitlyTrashed = null;
    public $exportLinks = null;
    public $fileExtension = null;
    public $folderColorRgb = null;
    public $fullFileExtension = null;
    public $hasAugmentedPermissions = null;
    public $hasThumbnail = null;
    public $headRevisionId = null;
    public $iconLink = 'https://drive-thirdparty.googleusercontent.com/16/type/application/vnd.google-apps.document';
    public $id = '1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k';
    public $isAppAuthorized = null;
    public $kind = null;
    public $md5Checksum = null;
    public $mimeType = 'application/vnd.google-apps.document';
    public $modifiedByMe = null;
    public $modifiedByMeTime = null;
    public $modifiedTime = '2018-12-22T01:12:30.736Z';
    public $name = 'Interview Template v0.1';
    public $originalFilename = null;
    public $ownedByMe = null;
    public $parents = null;
    public $permissionIds = null;
    public $properties = null;
    public $quotaBytesUsed = null;
    public $shared = null;
    public $sharedWithMeTime = null;
    public $size = null;
    public $spaces = null;
    public $starred = false;
    public $teamDriveId = null;
    public $thumbnailLink = 'https://docs.google.com/a/trytrig.com/feeds/vt?gd=true&id=1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k&v=14&s=AMedNnoAAAAAXpUOTCtmc4zTbEZ6g0EPywj-ypToA8-U&sz=s220';
    public $thumbnailVersion = null;
    public $trashed = null;
    public $trashedTime = null;
    public $version = null;
    public $viewedByMe = null;
    public $viewedByMeTime = '2018-12-22T01:12:30.736Z';
    public $viewersCanCopyContent = null;
    public $webContentLink = null;
    public $webViewLink = 'https://docs.google.com/document/d/1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k/edit?usp=drivesdk';
    public $writersCanShare = null;
}
