<?php

namespace Tests\Feature\Modules\User;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Exceptions\OauthMissingTokens;
use App\Modules\User\UserRepository;
use App\Modules\User\UserService;
use Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Queue;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use CreateOauthConnection;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->userService = app(UserService::class);
        $this->userRepo = app(UserRepository::class);
    }

    /**
     * Test creating a name for a user.
     */
    public function testName(): void
    {
        $user = $this->userRepo->findByEmail(Config::get('constants.seed.email'));

        self::assertEquals(
            $this->userService->getName($user),
            Config::get('constants.seed.first_name').' '.Config::get('constants.seed.last_name')
        );
    }

    /**
     * Test creating a name for a user with no name.
     */
    public function testNameNoName(): void
    {
        $email = 'sam_sung@example.com';
        $this->json('POST', 'register', [
            'email' => $email, 'password' => 'password', 'terms' => true,
        ]);

        $user = $this->userRepo->findByEmail($email);
        self::assertEquals('sam_sung (at) example.com', $this->userService->getName($user));
    }

    /**
     * Test syncing all integrations.
     *
     * @throws OauthMissingTokens
     */
    public function testSyncAll(): void
    {
        Queue::fake();

        $user = User::find(1);
        $workspace = Workspace::find(1);
        $this->createOauthConnection($user, $workspace);

        $this->userService->syncAllIntegrations($user, $workspace);

        Queue::assertPushed(SyncCards::class, 1);
    }
}
