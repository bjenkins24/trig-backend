<?php

namespace Tests\Feature\Modules\User;

use App\Jobs\SyncCards;
use App\Models\User;
use App\Modules\User\UserRepository;
use App\Modules\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Traits\CreateOauthConnection;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreateOauthConnection;

    public function setUp(): void
    {
        parent::setUp();
        $this->userService = app(UserService::class);
        $this->userRepo = app(UserRepository::class);
    }

    /**
     * Test creating a name for a user.
     *
     * @return void
     */
    public function testName()
    {
        $user = $this->userRepo->findByEmail(\Config::get('constants.seed.email'));

        $this->assertEquals(
            $this->userService->getName($user),
            \Config::get('constants.seed.first_name').' '.\Config::get('constants.seed.last_name')
        );
    }

    /**
     * Test creating a name for a user with no name.
     *
     * @return void
     */
    public function testNameNoName()
    {
        $email = 'sam_sung@example.com';
        $response = $this->json('POST', 'register', [
            'email' => $email, 'password' => 'password', 'terms' => true,
        ]);

        $user = $this->userRepo->findByEmail($email);
        $this->assertEquals($this->userService->getName($user), 'sam_sung (at) example.com');
    }

    /**
     * Test syncing all integrations.
     *
     * @return void
     */
    public function testSyncAll()
    {
        \Queue::fake();

        $user = User::find(1);
        $this->createOauthConnection($user);

        $this->userService->syncAllIntegrations($user);

        \Queue::assertPushed(SyncCards::class, 1);
    }
}
