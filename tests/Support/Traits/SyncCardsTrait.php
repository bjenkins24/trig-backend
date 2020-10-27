<?php

namespace Tests\Support\Traits;

use App\Models\User;
use App\Modules\Card\Integrations\Google\GoogleIntegration;
use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use App\Utils\FileHelper;
use Illuminate\Support\Collection;

trait SyncCardsTrait
{
    use CreateOauthConnection;

    private function getMockThumbnail(): Collection
    {
        return collect([
            'thumbnail' => 'coolthumbnail',
            'extension' => 'jpg',
            'width'     => 200,
            'height'    => 500,
        ]);
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    private function getSetup(?User $user = null, ?array $data = null, ?string $service = 'google', ?bool $refreshDb = true): array
    {
        if ($refreshDb) {
            $this->refreshDb();
        }
        if (! $user) {
            $user = User::find(1);
            $this->createOauthConnection($user);
        }
        if (null === $data) {
            $data = $this->getMockData();
        }
        foreach ($data as $key => $datum) {
            $data[$key]['data']['user_id'] = $user->id;
        }
        $syncCards = $this->getBase($data, $service);

        return [$syncCards, $data, $user];
    }

    private function getMockData(): array
    {
        return [
            [
                'data' => [
                    'delete'             => false,
                    'card_type'          => 'application/vnd.google-apps.document',
                    'url'                => 'https://docs.google.com/document/d/1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k/edit?usp=drivesdk',
                    'foreign_id'         => '1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k',
                    'title'              => 'Interview Template v0.1',
                    'description'        => 'My cool description',
                    'actual_created_at'  => '2018-12-22 12:30:00',
                    'actual_modified_at' => '2018-12-22 12:30:00',
                    'thumbnail_uri'      => 'https://docs.google.com/a/trytrig.com/feeds/vt?gd=true&id=1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k&v=14&s=AMedNnoAAAAAXpUOTCtmc4zTbEZ6g0EPywj-ypToA8-U&sz=s220',
                ],
                'permissions' => [
                    'users'      => [],
                    'link_share' => [],
                ],
            ],
        ];
    }

    /**
     * @throws OauthIntegrationNotFound
     */
    private function getBase(?array $data = null, ?string $service = 'google'): SyncCardsIntegration
    {
        if (null === $data) {
            $data = $this->getMockData();
        }
        $this->mock(GoogleIntegration::class, static function ($mock) use ($data) {
            $mock->shouldReceive('getAllCardData')->andReturn($data);
            $mock->shouldReceive('getIntegrationKey')->andReturn('google');
        });
        $this->partialMock(FileHelper::class, function ($mock) {
            $mock->shouldReceive('fileGetContents');
            $mock->shouldReceive('getImageSizeFromString')->andReturn([
                0      => $this->getMockThumbnail()->get('width'),
                1      => $this->getMockThumbnail()->get('height'),
                'mime' => 'image/jpg',
            ]);
        });

        return app(OauthIntegrationService::class)->makeSyncCards($service);
    }
}
