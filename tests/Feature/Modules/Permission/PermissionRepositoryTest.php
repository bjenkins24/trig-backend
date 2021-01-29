<?php

namespace Tests\Feature\Modules\Permission;

use App\Models\Card;
use App\Modules\Permission\PermissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test creating a permission for a person with createEmail.
     *
     * @return void
     */
    public function testCreatePersonPermission()
    {
        $card = app(Card::class)->first();
        $personEmail = 'sam_sung@example.com';
        $permission = app(PermissionRepository::class)->createEmail($card, 'reader', $personEmail);

        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => 'App\Models\Card',
            'permissionable_id'   => 1,
            'capability_id'       => 2,
        ]);

        $this->assertDatabaseHas('permission_types', [
            'typeable_type' => 'App\Models\Person',
            'typeable_id'   => 1,
            'permission_id' => $permission->id,
        ]);

        $this->assertDatabasehas('people', [
            'email' => $personEmail,
        ]);
    }

    /**
     * Test creating permission for a user.
     *
     * @return void
     */
    public function testCreateUserPermission()
    {
        $card = app(Card::class)->first();
        $permissionRepo = app(PermissionRepository::class);
        $email = \Config::get('constants.seed.email');
        $permission = $permissionRepo->createEmail($card, 'writer', $email);

        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => 'App\Models\Card',
            'permissionable_id'   => 1,
            'capability_id'       => 1,
        ]);

        $this->assertDatabaseHas('permission_types', [
            'typeable_type' => 'App\Models\User',
            'typeable_id'   => 1,
            'permission_id' => $permission->id,
        ]);

        $this->assertDatabaseMissing('people', [
            'email' => $email,
        ]);
    }

    /**
     * Test creating anyone permissions.
     *
     * @return void
     */
    public function testCreateAnyone()
    {
        $card = app(Card::class)->first();
        $permissionRepo = app(PermissionRepository::class);
        $email = \Config::get('constants.seed.email');
        $permission = $permissionRepo->createAnyone($card, 'writer');

        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => 'App\Models\Card',
            'permissionable_id'   => 1,
            'capability_id'       => 1,
        ]);

        $this->assertDatabaseHas('permission_types', [
            'typeable_type' => null,
            'typeable_id'   => null,
            'permission_id' => $permission->id,
        ]);
    }
}
