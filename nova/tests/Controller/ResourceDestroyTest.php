<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\IdFilter;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;

class ResourceDestroyTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanDestroyResources()
    {
        $role = factory(Role::class)->create();
        $user = factory(User::class)->create();
        $role->users()->attach($user);
        $role2 = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/roles', [
                            'resources' => [$role->id, $role2->id],
                        ]);

        $response->assertStatus(200);

        $this->assertCount(0, Role::all());
        $this->assertCount(1, DB::table('user_roles')->get());

        $this->assertCount(2, ActionEvent::all());
        $this->assertEquals('Delete', ActionEvent::first()->name);
        $this->assertEquals($role->id, ActionEvent::where('actionable_id', $role->id)->first()->target_id);
    }

    public function testDestroyingResourceCanPruneAttachmentRecords()
    {
        $_SERVER['__nova.role.prunable'] = true;

        $role = factory(Role::class)->create();
        $user = factory(User::class)->create();
        $role->users()->attach($user);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/roles', [
                            'resources' => [$role->id],
                        ]);

        unset($_SERVER['__nova.role.prunable']);

        $response->assertStatus(200);

        $this->assertCount(0, Role::all());
        $this->assertCount(0, DB::table('user_roles')->get());
    }

    public function testCanDestroyResourcesViaSearch()
    {
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/roles?search=1', [
                            'resources' => 'all',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, Role::all());

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Delete', ActionEvent::first()->name);
        $this->assertEquals($role->id, ActionEvent::where('actionable_id', $role->id)->first()->target_id);
    }

    public function testCanDestroyResourcesViaFilters()
    {
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();

        $filters = base64_encode(json_encode([
            [
                'class' => IdFilter::class,
                'value' => 1,
            ],
        ]));

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/roles?filters='.$filters, [
                            'resources' => 'all',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, Role::all());

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Delete', ActionEvent::first()->name);
        $this->assertEquals($role->id, ActionEvent::where('actionable_id', $role->id)->first()->target_id);
    }

    public function testDestroyingSoftDeletedResourcesKeepsPreviousActionEvents()
    {
        $user = factory(User::class)->create();
        $this->assertNull($user->deleted_at);

        ActionEvent::forResourceUpdate($user, $user)->save();

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users', [
                            'resources' => [$user->id],
                        ])
                        ->assertOk();

        $user = $user->fresh();
        $this->assertNotNull($user->deleted_at);

        $this->assertCount(2, ActionEvent::all());
        $latestActionEvent = ActionEvent::latest('id')->first();

        $this->assertEquals('Delete', $latestActionEvent->name);
        $this->assertEquals($user->id, $latestActionEvent->target->id);
        $this->assertTrue($user->is($latestActionEvent->target));

        $response = $this->withExceptionHandling()
            ->putJson('/nova-api/users/restore', [
                'resources' => [$user->id],
            ])->assertOk();

        $this->assertCount(3, ActionEvent::all());
        $latestActionEvent = ActionEvent::latest('id')->first();
        $this->assertEquals('Restore', $latestActionEvent->name);
        $this->assertEquals($user->id, $latestActionEvent->target->id);
        $this->assertTrue($user->is($latestActionEvent->target));
    }

    public function testCantDestroyResourcesNotAuthorizedToDestroy()
    {
        $user = factory(User::class)->create();
        $this->assertNull($user->deleted_at);

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.deletable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users', [
                            'resources' => [$user->id],
                        ]);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.deletable']);

        $response->assertStatus(200);

        $user = $user->fresh();
        $this->assertNull($user->deleted_at);

        $this->assertCount(0, ActionEvent::all());
    }

    public function testCanDestroyAllMatching()
    {
        factory(Post::class)->times(250)->create();

        $response = $this->withExceptionHandling()
            ->deleteJson('/nova-api/posts', [
                'resources' => 'all',
            ]);

        $response->assertStatus(200);

        $this->assertEquals(0, Post::count());

        $this->assertEquals(250, ActionEvent::count());
        $this->assertEquals('Delete', ActionEvent::first()->name);
    }

    public function testResourceCanRedirectToCustomUriOnDeletion()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
            ->deleteJson('/nova-api/users-with-redirects', [
                'resources' => [$user->id],
            ]);

        $response->assertJson(['redirect' => 'https://laravel.com']);
    }

    public function testActionEventShouldHonorCustomPolymorphicTypeForSoftDeletions()
    {
        Relation::morphMap(['role' => Role::class]);

        $role = factory(Role::class)->create();
        $user = factory(User::class)->create();
        $role->users()->attach($user);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/roles', [
                            'resources' => [$role->id],
                        ]);

        $actionEvent = ActionEvent::first();

        $this->assertEquals('Delete', $actionEvent->name);

        $this->assertEquals('role', $actionEvent->actionable_type);
        $this->assertEquals($role->id, $actionEvent->actionable_id);

        $this->assertEquals('role', $actionEvent->target_type);
        $this->assertEquals($role->id, $actionEvent->target_id);

        $this->assertEquals('role', $actionEvent->model_type);
        $this->assertEquals($role->id, $actionEvent->model_id);

        Relation::morphMap([], false);
    }

    public function testShouldStoreActionEventOnCorrectConnectionWhenDestroying()
    {
        $this->setupActionEventsOnSeparateConnection();

        $role = factory(Role::class)->create();
        $user = factory(User::class)->create();
        $role->users()->attach($user);
        $role2 = factory(Role::class)->create();

        $response = $this->withoutExceptionHandling()
            ->deleteJson('/nova-api/roles', [
                'resources' => [$role->id, $role2->id],
            ]);

        $response->assertStatus(200);

        $this->assertCount(0, Role::all());
        $this->assertCount(1, DB::table('user_roles')->get());

        $this->assertCount(0, DB::connection('sqlite')->table('action_events')->get());
        $this->assertCount(2, DB::connection('sqlite-custom')->table('action_events')->get());

        tap(Nova::actionEvent()->first(), function ($actionEvent) use ($role) {
            $this->assertEquals('Delete', $actionEvent->name);
            $this->assertEquals($role->id, $actionEvent->target_id);
        });
    }
}
