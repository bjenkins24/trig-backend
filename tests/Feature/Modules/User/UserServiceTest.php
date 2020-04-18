<?php

namespace Tests\Feature\Modules\User;

use App\Models\User;
use App\Modules\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private function getService()
    {
        return app(UserService::class);
    }

    /**
     * Test creating a name for a user.
     *
     * @return void
     */
    public function testName()
    {
        $userService = $this->getService();
        $user = $userService->findByEmail(\Config::get('constants.seed.email'));

        $this->assertEquals(
            $userService->getName($user),
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

        $userService = $this->getService();
        $user = $userService->findByEmail($email);
        $this->assertEquals($userService->getName($user), 'sam_sung (at) example.com');
    }
}
