<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test creating a name for a user.
     *
     * @return void
     */
    public function testName()
    {
        $user = User::where('email', Config::get('constants.seed.email'))->first();
        $this->assertEquals(
            $user->name(),
            Config::get('constants.seed.first_name').' '.Config::get('constants.seed.last_name')
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

        $user = User::where('email', $email)->first();
        $this->assertEquals($user->name(), 'sam_sung (at) example.com');
    }
}
