<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteUser;
use App\Models\User;
use App\Modules\User\UserRepository;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Throwable
     */
    public function testDeleteUser(): void
    {
        $userJob = new DeleteUser(User::find(1));
        $userJob->handle();

        $this->assertDatabaseHas('users', ['id' => 1]);

        $user = User::find(1);
        $user->setProperties(['tagged_for_deletion' => true]);
        $user->save();

        $userJob = new DeleteUser(User::find(1));
        $userJob->handle();
        $this->assertDatabaseMissing('users', ['id' => 1]);

        $this->mock(UserRepository::class, static function ($mock) {
            $mock->shouldReceive('delete')->andThrow(new Exception('random'));
        });
        self::assertFalse($userJob->handle());
    }
}
