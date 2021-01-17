<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;

class ResourceUpdateTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanUpdateResources()
    {
        $user = factory(User::class)->create([
            'name'  => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
        ]);

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'     => 'David Hemphill',
                            'email'    => 'david@laravel.com',
                            'password' => 'password',
                        ]);

        $response->assertStatus(200);

        $user = $user->fresh();
        $this->assertEquals('David Hemphill', $user->name);
        $this->assertEquals('david@laravel.com', $user->email);

        $this->assertCount(1, ActionEvent::all());

        $actionEvent = ActionEvent::first();

        $this->assertEquals('Update', $actionEvent->name);
        $this->assertEquals($user->id, $actionEvent->target->id);
        $this->assertSubset(['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com'], $actionEvent->original);
        $this->assertSubset(['name' => 'David Hemphill', 'email' => 'david@laravel.com'], $actionEvent->changes);
        $this->assertTrue($user->is(ActionEvent::first()->target));
    }

    public function testCantUpdateResourceFieldsThatArentAuthorized()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'       => 'Taylor Otwell',
                            'email'      => 'taylor@laravel.com',
                            'password'   => 'password',
                            'restricted' => 'No',
                        ]);

        $response->assertStatus(200);

        $user = $user->fresh();
        $this->assertEquals('Taylor Otwell', $user->name);
        $this->assertEquals('taylor@laravel.com', $user->email);
        $this->assertEquals('Yes', $user->restricted);
    }

    public function testCantUpdateResourcesThatHaveBeenEditedSinceRetrieval()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'          => 'Taylor Otwell',
                            'email'         => 'taylor@laravel.com',
                            'password'      => 'password',
                            '_retrieved_at' => now()->subHours(1)->getTimestamp(),
                        ]);

        $response->assertStatus(409);
    }

    public function testCanDisableTrafficCop()
    {
        $_SERVER['nova.user.trafficCop'] = false;

        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'          => 'Taylor Otwell',
                            'email'         => 'taylor@laravel.com',
                            'password'      => 'password',
                            '_retrieved_at' => now()->subHours(1)->getTimestamp(),
                        ]);

        $response->assertStatus(200);
    }

    public function testMustBeAuthorizedToUpdateResource()
    {
        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.updatable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'     => 'Taylor Otwell',
                            'email'    => 'taylor@laravel.com',
                            'password' => 'password',
                        ]);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.updatable']);

        $response->assertStatus(403);
    }

    public function testMustBeAuthorizedToRelateRelatedResourceToUpdateAResourceThatItBelongsTo()
    {
        $post = factory(Post::class)->create();

        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user3 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/posts/'.$post->id, [
                            'user'  => $user3->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $response->assertStatus(422);
    }

    public function testParentResourcePolicyMayPreventAddingRelatedResources()
    {
        $post = factory(Post::class)->create();
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/posts/'.$post->id, [
                            'user'  => $user->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $response->assertStatus(200);

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.addPost'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/posts/'.$post->id, [
                            'user'  => $user->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.addPost']);

        $response->assertStatus(422);
        $this->assertInstanceOf(User::class, $_SERVER['nova.user.addPostModel']);
        $this->assertEquals($user->id, $_SERVER['nova.user.addPostModel']->id);

        unset($_SERVER['nova.user.addPostModel']);
    }

    public function testCanUpdateSoftDeletedResources()
    {
        $user = factory(User::class)->create();
        $user->delete();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'     => 'Taylor Otwell',
                            'email'    => 'taylor@laravel.com',
                            'password' => 'password',
                        ]);

        $response->assertStatus(200);

        $user = $user->fresh();
        $this->assertEquals('Taylor Otwell', $user->name);
        $this->assertEquals('taylor@laravel.com', $user->email);

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Update', ActionEvent::first()->name);
        $this->assertEquals($user->id, ActionEvent::first()->target->id);
        $this->assertTrue($user->is(ActionEvent::first()->target));
    }

    public function testUserCanMaintainSameEmailWithoutUniqueErrors()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'     => $user->name,
                            'email'    => $user->email,
                            'password' => $user->password,
                        ]);

        $response->assertStatus(200);
    }

    public function testValidationRulesAreApplied()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/users/'.$user->id, [
                            'name'     => $user->name,
                            'email'    => $user2->email,
                            'password' => $user->password,
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    public function testResourceWithParentCanBeUpdated()
    {
        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/posts/'.$post->id, [
                            'user'  => $post->user->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $response->assertStatus(200);
    }

    public function testParentResourceMustExist()
    {
        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/posts/'.$post->id, [
                            'user'  => 100,
                            'title' => 'Fake Title',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user']);
    }

    public function testActionEventShouldHonorCustomPolymorphicTypeForResourceUpdate()
    {
        Relation::morphMap(['post' => Post::class]);

        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/posts/'.$post->id, [
                            'user'  => $post->user_id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $actionEvent = ActionEvent::first();

        $this->assertEquals('Update', $actionEvent->name);

        $this->assertEquals('post', $actionEvent->actionable_type);
        $this->assertEquals($post->id, $actionEvent->actionable_id);

        $this->assertEquals('post', $actionEvent->target_type);
        $this->assertEquals($post->id, $actionEvent->target_id);

        $this->assertEquals('post', $actionEvent->model_type);
        $this->assertEquals($post->id, $actionEvent->model_id);

        Relation::morphMap([], false);
    }

    public function testFieldsAreNotValidatedIfUserCantSeeThem()
    {
        $_SERVER['weight-field.canSee'] = false;
        $_SERVER['weight-field.readonly'] = false;

        $user = factory(User::class)->create(['weight' => 250]);

        $this->withExceptionHandling()
            ->putJson('/nova-api/users/'.$user->id, [
                'name'  => 'Taylor Otwell',
                'email' => 'taylor@laravel.com',
                // 'weight' => 190,
                'password' => 'password',
            ])
            ->assertOk();
    }

    public function testFieldsAreNotUpdatedIfUserCantSeeThem()
    {
        $_SERVER['weight-field.canSee'] = false;
        $_SERVER['weight-field.readonly'] = false;

        $user = factory(User::class)->create(['weight' => 250]);

        $this->withExceptionHandling()
            ->putJson('/nova-api/users/'.$user->id, [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'weight'   => 190,
                'password' => 'password',
            ])
            ->assertOk();

        $this->assertEquals(250, $user->fresh()->weight);
    }

    public function testReadonlyFieldsAreNotValidated()
    {
        $_SERVER['weight-field.canSee'] = true;
        $_SERVER['weight-field.readonly'] = true;

        $user = factory(User::class)->create(['weight' => 250]);

        $this->withExceptionHandling()
            ->putJson(sprintf('/nova-api/users/%s?editing=true&editMode=update', $user->id), [
                'name'  => 'Taylor Otwell',
                'email' => 'taylor@laravel.com',
                // 'weight' => 190,
                'password' => 'password',
            ])
            ->assertOk();
    }

    public function testReadonlyFieldsAreNotUpdated()
    {
        $_SERVER['weight-field.canSee'] = true;
        $_SERVER['weight-field.readonly'] = true;

        $user = factory(User::class)->create(['weight' => 250]);

        $this->withoutExceptionHandling()
            ->putJson(sprintf('/nova-api/users/%s?editing=true&editMode=update', $user->id), [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'weight'   => 190,
                'password' => 'password',
            ])
            ->assertOk();

        $this->assertEquals(250, $user->fresh()->weight);
    }

    public function testResourceCanRedirectToDefaultUriOnUpdate()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
            ->putJson('/nova-api/users/'.$user->id, [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ]);

        $response->assertJson(['redirect' => '/resources/users/1']);
    }

    public function testResourceCanRedirectToCustomUriOnUpdate()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
            ->putJson('/nova-api/users-with-redirects/'.$user->id, [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ]);

        $response->assertJson(['redirect' => 'https://google.com']);
    }

    public function testSelectResourceQueryCountOnUpdate()
    {
        $user = factory(User::class)->create(['weight' => 250]);

        DB::enableQueryLog();

        $this->withExceptionHandling()
             ->putJson('/nova-api/users/'.$user->id, [
                 'name'     => 'Taylor Otwell',
                 'email'    => 'taylor@laravel.com',
                 'password' => 'password',
             ])
             ->assertOk();

        DB::disableQueryLog();

        $queries = count(array_filter(DB::getQueryLog(), function ($log) {
            return 'select * from "users" where "users"."id" = ? limit 1' === $log['query'];
        }));

        $this->assertEquals(1, $queries);
    }

    public function testUsesExistingResourceOnRetrievingValidationRulesFromCallbacks()
    {
        $user = factory(User::class)->create(['email' => 'taylor@laravel.com']);

        $_SERVER['nova.user.fixedValuesOnUpdate'] = true;

        $this->withExceptionHandling()
             ->putJson('/nova-api/users/'.$user->id, [
                 'name'     => 'Taylor Otwell', // The name is required to be 'Taylor Otwell'
                 'email'    => 'taylor@laravel.com',
                 'password' => 'incorrectpassword', // The password is required to be 'taylorotwell'
             ])
             ->assertStatus(422);

        $this->withExceptionHandling()
             ->putJson('/nova-api/users/'.$user->id, [
                 'name'     => 'David Hemphill', // The name is required to be 'Taylor Otwell'
                 'email'    => 'taylor@laravel.com',
                 'password' => 'taylorotwell', // The password is required to be 'taylorotwell'
             ])
             ->assertStatus(422);

        $this->withExceptionHandling()
             ->putJson('/nova-api/users/'.$user->id, [
                 'name'     => 'Taylor Otwell', // The name is required to be 'Taylor Otwell'
                 'email'    => 'taylor@laravel.com',
                 'password' => 'taylorotwell', // The password is required to be 'taylorotwell'
             ])
             ->assertOk();

        unset($_SERVER['nova.user.fixedValuesOnUpdate']);

        $this->assertEquals('taylorotwell', $user->fresh()->password);
    }

    public function testShouldStoreActionEventOnCorrectConnectionWhenUpdating()
    {
        $this->setupActionEventsOnSeparateConnection();

        $user = factory(User::class)->create([
            'name'  => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
        ]);

        $response = $this->withExceptionHandling()
            ->putJson('/nova-api/users/'.$user->id, [
                'name'     => 'David Hemphill',
                'email'    => 'david@laravel.com',
                'password' => 'password',
            ]);

        $response->assertStatus(200);

        $this->assertCount(0, DB::connection('sqlite')->table('action_events')->get());
        $this->assertCount(1, DB::connection('sqlite-custom')->table('action_events')->get());

        tap(Nova::actionEvent()->first(), function ($actionEvent) use ($user) {
            $this->assertEquals('Update', $actionEvent->name);
            $this->assertEquals($user->id, $actionEvent->target_id);
            $this->assertSubset(['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com'], $actionEvent->original);
            $this->assertSubset(['name' => 'David Hemphill', 'email' => 'david@laravel.com'], $actionEvent->changes);
        });
    }

    public function tearDown(): void
    {
        unset($_SERVER['weight-field.readonly']);
        unset($_SERVER['weight-field.canSee']);

        parent::tearDown();
    }
}
