<?php

namespace Tests\Support\Traits;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\Card\Helpers\ThumbnailHelper;
use App\Modules\Card\Integrations\Google\GoogleIntegration;
use App\Modules\Card\Integrations\SyncCards as SyncCardsIntegration;
use App\Modules\OauthIntegration\Exceptions\OauthIntegrationNotFound;
use App\Modules\OauthIntegration\OauthIntegrationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

trait SyncCardsTrait
{
    use CreateOauthConnection;

    private function getMockThumbnail(): Collection
    {
        return collect([
            'thumbnail' => 'coolthumbnail',
            'extension' => 'jpg',
        ]);
    }

    /**
     * @throws OauthIntegrationNotFound
     * @throws OauthMissingTokens
     */
    private function getSetup(?User $user = null, ?Workspace $workspace = null, ?array $data = null, ?string $service = 'google', ?bool $refreshDb = true): array
    {
        Queue::fake();
        if ($refreshDb) {
            $this->refreshDb();
        }

        if (! $workspace) {
            $workspace = Workspace::find(1);
        }
        if (! $user) {
            $user = User::find(1);
            $this->createOauthConnection($user, $workspace);
        }
        if (null === $data) {
            $data = $this->getMockData();
        }
        foreach ($data as $key => $datum) {
            $data[$key]['data']['user_id'] = $user->id;
        }
        $syncCards = $this->getBase($data, $service);

        return [$syncCards, $data, $user, $workspace];
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
                    'actual_updated_at'  => '2018-12-22 12:30:00',
                    'image'              => 'https://docs.google.com/a/trytrig.com/feeds/vt?gd=true&id=1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k&v=14&s=AMedNnoAAAAAXpUOTCtmc4zTbEZ6g0EPywj-ypToA8-U&sz=s220',
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
        $this->partialMock(ThumbnailHelper::class, static function ($mock) {
            $mock->shouldReceive('saveThumbnail');
        });

        return app(OauthIntegrationService::class)->makeSyncCards($service);
    }
}
