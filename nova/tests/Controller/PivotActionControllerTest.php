<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Tests\Fixtures\FailingPivotAction;
use Laravel\Nova\Tests\Fixtures\NoopAction;
use Laravel\Nova\Tests\Fixtures\NoopActionWithPivotHandle;
use Laravel\Nova\Tests\Fixtures\QueuedAction;
use Laravel\Nova\Tests\Fixtures\QueuedUpdateStatusAction;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\RoleAssignment;
use Laravel\Nova\Tests\Fixtures\RolePolicy;
use Laravel\Nova\Tests\Fixtures\UpdateStatusAction;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class PivotActionControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function tearDown(): void
    {
        unset($_SERVER['queuedAction.applied']);
        unset($_SERVER['queuedAction.appliedFields']);
        unset($_SERVER['queuedResourceAction.applied']);
        unset($_SERVER['queuedResourceAction.appliedFields']);

        parent::tearDown();
    }

    public function testCanRetrievePivotActionsForAResource()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/roles/actions?viaResource=users&viaResourceId=1&viaRelationship=roles');

        $response->assertStatus(200);
        $this->assertInstanceOf(Action::class, $response->original['actions'][0]);

        $this->assertEquals('Pivot', $response->original['pivotActions']['name']);
        $this->assertInstanceOf(Action::class, $response->original['pivotActions']['actions'][0]);
    }

    public function testPivotActionsCanHaveACustomPivotName()
    {
        $_SERVER['nova.user.rolePivotName'] = 'Role Assignment';

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/roles/actions?viaResource=users&viaResourceId=1&viaRelationship=roles');

        $response->assertStatus(200);
        $this->assertEquals('Role Assignment', $response->original['pivotActions']['name']);

        unset($_SERVER['nova.user.rolePivotName']);
    }

    public function testPivotActionsCanBeAppliedAndPassPivotModelsToTheActions()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(NoopAction::class), [
                            'resources' => $role->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $response->assertStatus(200);

        $this->assertEquals($user->id, NoopAction::$applied[0][0]->user_id);
        $this->assertEquals($role->id, NoopAction::$applied[0][0]->role_id);

        $this->assertEquals('Taylor Otwell', NoopAction::$appliedFields[0]->test);
        $this->assertEquals('callback', NoopAction::$appliedFields[0]->callbacks()['callback']());

        $this->assertCount(1, ActionEvent::all());
        $actionEvent = ActionEvent::first();
        $this->assertEquals(RoleAssignment::class, $actionEvent->model_type);
        $this->assertEquals($user->fresh()->roles->first()->pivot->getKey(), $actionEvent->model_id);
        $this->assertEquals(User::class, $actionEvent->actionable_type);
        $this->assertEquals('Noop Action', $actionEvent->name);
        $this->assertEquals(['test' => 'Taylor Otwell'], unserialize($actionEvent->fields));
        $this->assertEquals('finished', $actionEvent->status);
    }

    public function testPivotActionCanBeAppliedIfAuthorizedToUpdateResource()
    {
        $_SERVER['nova.role.authorizable'] = true;
        $_SERVER['nova.role.updatable'] = true;

        Gate::policy(Role::class, RolePolicy::class);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(NoopAction::class), [
                            'resources' => $role->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        unset($_SERVER['nova.role.authorizable']);
        unset($_SERVER['nova.role.updatable']);

        $response->assertStatus(200);
        $this->assertNotEmpty(NoopAction::$applied);
        $this->assertCount(1, ActionEvent::all());
    }

    public function testPivotActionCantBeAppliedIfNotAuthorizedToUpdateResource()
    {
        // TODO: Currently, pivot actions do not check the "update" ability
        // of either side of the relationship. Authorization is only
        // controlled by the canSee / canRun methods of the action
        return;

        $_SERVER['nova.role.authorizable'] = true;
        $_SERVER['nova.role.updatable'] = false;

        Gate::policy(Role::class, RolePolicy::class);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(NoopAction::class), [
                            'resources' => $role->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        unset($_SERVER['nova.role.authorizable']);
        unset($_SERVER['nova.role.updatable']);

        $response->assertStatus(200);
        $this->assertEmpty(NoopAction::$applied);
        $this->assertCount(0, ActionEvent::all());
    }

    public function testPivotActionsCanBeHandledByCustomMethodName()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(NoopActionWithPivotHandle::class), [
                            'resources' => $role->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $response->assertStatus(200);

        $this->assertEquals($user->id, NoopActionWithPivotHandle::$applied[0][0]->user_id);
        $this->assertEquals($role->id, NoopActionWithPivotHandle::$applied[0][0]->role_id);
        $this->assertEquals('Taylor Otwell', NoopActionWithPivotHandle::$appliedFields[0]->test);
        $this->assertEquals('callback', NoopActionWithPivotHandle::$appliedFields[0]->callbacks()['callback']());
    }

    public function testPivotActionsCanBeHandledForEntireResource()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(NoopActionWithPivotHandle::class), [
                            'resources' => 'all',
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $response->assertStatus(200);

        $this->assertEquals($user->id, NoopActionWithPivotHandle::$applied[0][0]->user_id);
        $this->assertEquals($role->id, NoopActionWithPivotHandle::$applied[0][0]->role_id);
        $this->assertEquals('Taylor Otwell', NoopActionWithPivotHandle::$appliedFields[0]->test);
        $this->assertEquals('callback', NoopActionWithPivotHandle::$appliedFields[0]->callbacks()['callback']());
    }

    public function testPivotActionsCanUpdateSingleEventStatuses()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role);
        $user->roles()->attach($role2);

        $response = $this->withExceptionHandling()
                        ->post($this->pivotActionUriFor(UpdateStatusAction::class), [
                            'resources' => implode(',', [$role->id, $role2->id]),
                        ]);

        $response->assertStatus(200);
        $this->assertCount(2, ActionEvent::all());
        $this->assertEquals('failed', ActionEvent::where('model_id', 1)->first()->status);
        $this->assertEquals('finished', ActionEvent::where('model_id', 2)->first()->status);
    }

    public function testFailedPivotActionsAreMarkedAsFailed()
    {
        config(['queue.default' => 'sync']);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(FailingPivotAction::class), [
                            'resources' => $role->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('failed', ActionEvent::first()->status);
        $this->assertTrue(FailingPivotAction::$failedForRoleAssignment);
    }

    public function testFailedPivotActionsForEntireResourceAreMarkedAsFailed()
    {
        config(['queue.default' => 'sync']);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(FailingPivotAction::class), [
                            'resources' => 'all',
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('failed', ActionEvent::first()->status);
        $this->assertTrue(FailingPivotAction::$failedForRoleAssignment);
    }

    public function testQueuedPivotActionsCanBeDispatched()
    {
        config(['queue.default' => 'sync']);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role2);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(QueuedAction::class), [
                            'resources' => $role2->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $response->assertStatus(200);

        $this->assertEquals($user->id, $_SERVER['queuedAction.applied'][0][0]->user_id);
        $this->assertEquals($role2->id, $_SERVER['queuedAction.applied'][0][0]->role_id);
        $this->assertEquals('Taylor Otwell', $_SERVER['queuedAction.appliedFields'][0]->test);

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals(User::class, ActionEvent::first()->actionable_type);
        $this->assertEquals(Role::class, ActionEvent::first()->target_type);
        $this->assertEquals(1, ActionEvent::first()->actionable_id);
        $this->assertEquals(2, ActionEvent::first()->target_id);
        $this->assertEquals('finished', ActionEvent::first()->status);
    }

    public function testQueuedPivotActionsCanUpdateSingleEventStatuses()
    {
        config(['queue.default' => 'redis']);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role);
        $user->roles()->attach($role2);

        $response = $this->withExceptionHandling()
                        ->post($this->pivotActionUriFor(QueuedUpdateStatusAction::class), [
                            'resources' => implode(',', [$role->id, $role2->id]),
                        ]);

        $response->assertStatus(200);
        $this->assertCount(2, ActionEvent::all());
        $this->assertEquals('waiting', ActionEvent::where('model_id', 1)->first()->status);
        $this->assertEquals('waiting', ActionEvent::where('model_id', 2)->first()->status);

        $this->work();

        $this->assertEquals('failed', ActionEvent::where('model_id', 1)->first()->status);
        $this->assertEquals('finished', ActionEvent::where('model_id', 2)->first()->status);
    }

    public function testActionEventShouldHonorCustomPolymorphicTypeForPivotAction()
    {
        config(['queue.default' => 'sync']);

        Relation::morphMap([
            'user'      => User::class,
            'role'      => Role::class,
            'role_user' => RoleAssignment::class,
        ]);

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->roles()->attach($role);

        $response = $this->withoutExceptionHandling()
                        ->post($this->pivotActionUriFor(FailingPivotAction::class), [
                            'resources' => $role->id,
                            'test'      => 'Taylor Otwell',
                            'callback'  => '',
                        ]);

        $actionEvent = ActionEvent::first();

        $this->assertEquals('Failing Pivot Action', $actionEvent->name);

        $this->assertEquals('user', $actionEvent->actionable_type);
        $this->assertEquals($user->id, $actionEvent->actionable_id);

        $this->assertEquals('role', $actionEvent->target_type);
        $this->assertEquals($role->id, $actionEvent->target_id);

        $this->assertEquals('role_user', $actionEvent->model_type);
        $this->assertEquals($user->roles->first->pivot->id, $actionEvent->model_id);

        Relation::morphMap([], false);
    }

    /**
     * Get a pivot action URL for the given action.
     *
     * @param string $action
     *
     * @return string
     */
    protected function pivotActionUriFor($action)
    {
        $key = (new $action())->uriKey();

        return '/nova-api/roles/action?action='.$key.'&pivotAction=true&viaResource=users&viaResourceId=1&viaRelationship=roles';
    }
}
