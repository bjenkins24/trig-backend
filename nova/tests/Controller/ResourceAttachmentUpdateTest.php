<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\RoleAssignment;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class ResourceAttachmentUpdateTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanUpdateAttachedResources()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role, ['admin' => 'Y']);

        $this->assertEquals('Y', $user->fresh()->roles->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                            'roles'           => $role->id,
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, $user->fresh()->roles);
        $this->assertEquals($role->id, $user->fresh()->roles->first()->id);
        $this->assertEquals('N', $user->fresh()->roles->first()->pivot->admin);

        $this->assertCount(1, ActionEvent::all());

        $actionEvent = ActionEvent::first();
        $this->assertEquals('Update Attached', $actionEvent->name);
        $this->assertEquals('finished', $actionEvent->status);

        $this->assertEquals($user->id, $actionEvent->target->id);
        $this->assertSubset(['admin' => 'Y'], $actionEvent->original);
        $this->assertSubset(['admin' => 'N'], $actionEvent->changes);
    }

    public function testPivotFieldsThatAreHiddenFromCreateAreSavedOnEdit()
    {
        $_SERVER['nova.roles.hideAdminWhenCreating'] = true;
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role, ['admin' => 'Y']);

        $this->assertEquals('Y', $user->fresh()->roles->first()->pivot->admin);

        $response = $this->withoutExceptionHandling()
            ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                'roles'           => $role->id,
                'admin'           => 'N',
                'pivot-update'    => 'N',
                'viaRelationship' => 'roles',
            ]);

        $response->assertStatus(200);

        $this->assertEquals('N', $user->fresh()->roles->first()->pivot->fresh()->admin);

        unset($_SERVER['nova.roles.hideAdminWhenCreating']);
    }

    public function testCantUpdatePivotFieldsThatArentAuthorized()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role, ['admin' => 'Y']);

        $this->assertEquals('Y', $user->fresh()->roles->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                            'roles'           => $role->id,
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'restricted'      => 'No',
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(200);

        $this->assertEquals('Yes', $user->fresh()->roles->first()->pivot->restricted);
    }

    public function testCanUpdateAttachedSoftDeletedResources()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->delete();
        $role->users()->attach($user, ['admin' => 'Y']);

        $this->assertEquals('Y', $role->fresh()->users()->withTrashed()->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/roles/'.$role->id.'/update-attached/users/'.$user->id, [
                            'users'           => $user->id,
                            'users_trashed'   => 'true',
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'viaRelationship' => 'users',
                        ]);

        $response->assertStatus(200);

        $users = $role->fresh()->users()->withTrashed()->get();

        $this->assertCount(1, $users);
        $this->assertEquals($role->id, $users->first()->id);
        $this->assertEquals('N', $users->first()->pivot->admin);
    }

    public function testCantUpdateAttachedResourcesIfRelatedResourceIsNotRelatable()
    {
        $user = factory(User::class)->create();

        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $role3 = factory(Role::class)->create();

        $user->roles()->attach($role3, ['admin' => 'Y']);

        $this->assertEquals('Y', $user->fresh()->roles->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                            'roles'           => $role3->id,
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(422);
        $this->assertFalse(isset($_SERVER['nova.user.relatableRoles']));
    }

    public function testResourceMaySpecifyCustomRelatableQueryCustomizer()
    {
        $user = factory(User::class)->create();

        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $role3 = factory(Role::class)->create();

        $_SERVER['nova.user.useCustomRelatableRoles'] = true;
        unset($_SERVER['nova.user.relatableRoles']);

        $user->roles()->attach($role3, ['admin' => 'Y']);

        $this->assertEquals('Y', $user->fresh()->roles->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                            'roles'           => $role3->id,
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'viaRelationship' => 'roles',
                        ]);

        unset($_SERVER['nova.user.useCustomRelatableRoles']);

        $this->assertNotNull($_SERVER['nova.user.relatableRoles']);
        $response->assertStatus(422);

        unset($_SERVER['nova.user.relatableRoles']);
    }

    public function test404IsReturnedIfResourceIsNotAttached()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/100', [
                            'roles'           => $role->id,
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(404);
    }

    public function testPivotDataIsValidated()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                            'roles'           => $role->id,
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['admin']);
    }

    public function testActionEventShouldHonorCustomPolymorphicTypeForAttachedResourceUpdate()
    {
        Relation::morphMap([
            'user'      => User::class,
            'role'      => Role::class,
            'role_user' => RoleAssignment::class,
        ]);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role, ['admin' => 'Y']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                            'roles'           => $role->id,
                            'admin'           => 'N',
                            'pivot-update'    => 'N',
                            'viaRelationship' => 'roles',
                        ]);

        $actionEvent = ActionEvent::first();

        $this->assertEquals('Update Attached', $actionEvent->name);

        $this->assertEquals('user', $actionEvent->actionable_type);
        $this->assertEquals($user->id, $actionEvent->actionable_id);

        $this->assertEquals('role', $actionEvent->target_type);
        $this->assertEquals($role->id, $actionEvent->target_id);

        $this->assertEquals('role_user', $actionEvent->model_type);
        $this->assertEquals($user->roles->first->pivot->id, $actionEvent->model_id);

        Relation::morphMap([], false);
    }

    public function testShouldStoreActionEventOnCorrectConnectionWhenUpdatingAttachments()
    {
        $this->setupActionEventsOnSeparateConnection();

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role, ['admin' => 'Y']);

        $this->assertEquals('Y', $user->fresh()->roles->first()->pivot->admin);

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                'roles'           => $role->id,
                'admin'           => 'N',
                'pivot-update'    => 'N',
                'viaRelationship' => 'roles',
            ]);

        $response->assertStatus(200);

        $this->assertCount(1, $user->fresh()->roles);
        $this->assertEquals($role->id, $user->fresh()->roles->first()->id);
        $this->assertEquals('N', $user->fresh()->roles->first()->pivot->admin);

        $this->assertCount(0, DB::connection('sqlite')->table('action_events')->get());
        $this->assertCount(1, DB::connection('sqlite-custom')->table('action_events')->get());

        tap(Nova::actionEvent()->first(), function ($actionEvent) use ($user) {
            $this->assertEquals('Update Attached', $actionEvent->name);
            $this->assertEquals('finished', $actionEvent->status);

            $this->assertEquals($user->id, $actionEvent->target_id);
            $this->assertSubset(['admin' => 'Y'], $actionEvent->original);
            $this->assertSubset(['admin' => 'N'], $actionEvent->changes);
        });
    }

    public function testAttachableResourceWithCustomRelationshipNameAreValidatedOnUpdate()
    {
        $_SERVER['nova.useRolesCustomAttribute'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                'roles'           => null,
                'viaRelationship' => 'userRoles',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('roles');

        unset($_SERVER['nova.useRolesCustomAttribute']);
    }

    public function testUpdateAttachResourceWithCustomRelationshipName()
    {
        $_SERVER['nova.useRolesCustomAttribute'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/users/'.$user->id.'/update-attached/roles/'.$role->id, [
                'roles'           => $role->id,
                'admin'           => 'Y',
                'viaRelationship' => 'userRoles',
            ]);

        $response->assertOk();

        unset($_SERVER['nova.useRolesCustomAttribute']);
    }
}
