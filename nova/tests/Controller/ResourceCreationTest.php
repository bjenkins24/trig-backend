<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\Address;
use Laravel\Nova\Tests\Fixtures\CustomKey;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Profile;
use Laravel\Nova\Tests\Fixtures\Recipient;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;

class ResourceCreationTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanCreateResources()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users', [
                            'name'     => 'Taylor Otwell',
                            'email'    => 'taylor@laravel.com',
                            'password' => 'password',
                        ]);

        $response->assertStatus(201);

        $user = User::first();
        $this->assertEquals('Taylor Otwell', $user->name);
        $this->assertEquals('taylor@laravel.com', $user->email);

        $actionEvent = ActionEvent::first();
        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Create', $actionEvent->name);
        $this->assertEquals($user->id, $actionEvent->target->id);
        $this->assertEmpty($actionEvent->original);
        $this->assertSubset([
            'name'  => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
        ], $actionEvent->changes);
        $this->assertTrue($user->is($actionEvent->target));
    }

    public function testCanReturnCustomPk()
    {
        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/custom-keys', [
            ]);

        $response->assertStatus(201);

        $model = CustomKey::first();

        $this->assertEquals($model->pk, $response->getData()->id);
    }

    public function testCanCreateResourcesWithNullRelation()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'title' => 'Test Post',
                            'slug'  => 'test-post',
                            'user'  => '',
                        ]);

        $response->assertStatus(201);

        $post = Post::first();

        $this->assertNull($post->user_id);
    }

    public function testCanCreateResourceFieldsThatArentAuthorized()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users', [
                            'name'       => 'Taylor Otwell',
                            'email'      => 'taylor@laravel.com',
                            'password'   => 'password',
                            'restricted' => 'No',
                        ]);

        $response->assertStatus(201);

        $user = User::first();
        $this->assertEquals('Taylor Otwell', $user->name);
        $this->assertEquals('taylor@laravel.com', $user->email);
        $this->assertEquals('Yes', $user->restricted);
    }

    public function testMustBeAuthorizedToCreateResource()
    {
        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.creatable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users', [
                            'name'     => 'Taylor Otwell',
                            'email'    => 'taylor@laravel.com',
                            'password' => 'password',
                        ]);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.creatable']);

        $response->assertStatus(403);
    }

    public function testValidationRulesAreApplied()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users', [
                            'password' => '',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'name',
            'email',
            'password',
        ]);

        $user = User::first();
        $this->assertNull($user);
    }

    public function testResourceWithParentCanBeCreated()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'user'  => $user->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $response->assertStatus(201);
    }

    public function testMustBeAuthorizedToRelateRelatedResourceToCreateAResourceThatItBelongsTo()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user3 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'user'  => $user3->id,
                            'title' => 'Fake Title',
                        ]);

        $response->assertStatus(422);

        // Ensure base User::relatableQuery was called...
        $this->assertFalse(isset($_SERVER['nova.post.relatableUsers']));
    }

    public function testResourceMaySpecifyCustomRelatableQueryCustomizer()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user3 = factory(User::class)->create();

        $_SERVER['nova.post.useCustomRelatableUsers'] = true;
        unset($_SERVER['nova.post.relatableUsers']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'user'  => $user3->id,
                            'title' => 'Fake Title',
                        ]);

        unset($_SERVER['nova.post.useCustomRelatableUsers']);

        $this->assertNotNull($_SERVER['nova.post.relatableUsers']);
        $response->assertStatus(422);

        unset($_SERVER['nova.post.relatableUsers']);
    }

    public function testParentResourcePolicyMayPreventAddingRelatedResources()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'user'  => $user->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $response->assertStatus(201);

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.addPost'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'user'  => $user->id,
                            'title' => 'Fake Title',
                        ]);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.addPost']);

        $response->assertStatus(422);
        $this->assertInstanceOf(User::class, $_SERVER['nova.user.addPostModel']);
        $this->assertEquals($user->id, $_SERVER['nova.user.addPostModel']->id);

        unset($_SERVER['nova.user.addPostModel']);
    }

    public function testParentResourceMustExist()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts', [
                            'user'  => 100,
                            'title' => 'Fake Title',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user']);
    }

    public function testCanCreateResourceViaParentResource()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts?viaResource=users&viaResourceId=1&viaRelationship=posts', [
                            'user'  => $user->id,
                            'title' => 'Fake Title',
                            'slug'  => 'fake-title',
                        ]);

        $response->assertStatus(201);
    }

    public function testRelatedResourceMustBeRelatableToCreateResourcesViaResource()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user3 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts?viaResource=users&viaResourceId=1&viaRelationship=posts', [
                            'user'  => $user3->id,
                            'title' => 'Fake Title',
                        ]);

        $response->assertStatus(422);
    }

    public function testResourceThatBelongsToParentViaHasOneCanBeCreated()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/addresses?viaResource=users&viaResourceId=1&viaRelationship=address', [
                            'user' => $user->id,
                            'name' => 'Fake Name',
                        ]);

        $response->assertStatus(201);
    }

    public function testResourceThatBelongsToWithCustomOwnerKey()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/recipients', [
                'user' => $user->id,
                'name' => 'Fake Name',
            ]);

        $response->assertStatus(201);

        $recipient = Recipient::query()->first();

        $this->assertEquals($user->email, $recipient->email);
    }

    public function testRelatedResourceCantBeFullForHasOneRelationships()
    {
        $user = factory(User::class)->create();
        $user->address()->save($address = factory(Address::class)->make());

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/addresses?viaResource=users&viaResourceId=1&viaRelationship=address', [
                            'user' => $user->id,
                            'name' => 'Fake Name',
                        ]);

        $response->assertStatus(422);
    }

    public function testRelatedResourceShouldBeAbleToBeUpdatedEvenWhenFull()
    {
        $user = factory(User::class)->create();
        $user->address()->save($address = factory(Address::class)->make());

        $response = $this->withExceptionHandling()
                        ->putJson('/nova-api/addresses/'.$address->id.'?viaResource=users&viaResourceId=1&viaRelationship=address', [
                            'user' => $user->id,
                            'name' => 'Fake Name',
                        ]);

        $response->assertStatus(200);
    }

    public function testNullHasOneResourceShouldBeAbleToBeUpdatedWithValue()
    {
        $user = factory(User::class)->create();
        $profile = factory(Profile::class)->create();

        $this->assertNull($profile->user_id);

        $response = $this->withoutExceptionHandling()
                            ->putJson('/nova-api/profiles/'.$profile->id, [
                                'user'  => $user->id,
                                'phone' => '555-555-5555',
                            ]);

        $response->assertStatus(200);
    }

    public function testCanCreateResourcesWithNullRelationWithoutAutonull()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class);

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/posts', [
                'title' => 'Test Post',
                'slug'  => 'test-post',
                'user'  => '',
            ]);

        $response->assertStatus(201);

        $post = Post::first();

        $this->assertNull($post->user_id);
    }

    public function testActionEventShouldHonorCustomPolymorphicTypeForResourceCreation()
    {
        Relation::morphMap(['user' => User::class]);

        $this->withExceptionHandling()
             ->postJson('/nova-api/users', [
                 'name'     => 'Taylor Otwell',
                 'email'    => 'taylor@laravel.com',
                 'password' => 'password',
             ]);

        $user = User::first();
        $actionEvent = ActionEvent::first();

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Create', $actionEvent->name);

        $this->assertEquals('user', $actionEvent->actionable_type);
        $this->assertEquals($user->id, $actionEvent->actionable_id);

        $this->assertEquals('user', $actionEvent->target_type);
        $this->assertEquals($user->id, $actionEvent->target_id);

        $this->assertEquals('user', $actionEvent->model_type);
        $this->assertEquals($user->id, $actionEvent->model_id);

        $this->assertTrue($user->is($actionEvent->target));

        Relation::morphMap([], false);
    }

    public function testCanCreateResourcesWithKeyValueField()
    {
        $response = $this->withoutExceptionHandling()
                        ->postJson('/nova-api/users', [
                            'name'     => 'David Hemphill',
                            'email'    => 'david@laravel.com',
                            'password' => 'password',
                            'meta'     => json_encode([
                                'age'    => 34,
                                'weight' => 170,
                                'extra'  => [
                                    'nicknames' => ['Hempy', 'Hemp', 'Internet Ghost'],
                                ],
                            ]),
                        ]);

        $response->assertStatus(201);

        $user = User::first();

        $this->assertEquals([
            'age'    => 34,
            'weight' => 170,
            'extra'  => ['nicknames' => ['Hempy', 'Hemp', 'Internet Ghost']],
        ],
            $user->meta
        );
    }

    public function testFieldsAreNotValidatedIfUserCantSeeThem()
    {
        $_SERVER['weight-field.canSee'] = false;
        $_SERVER['weight-field.readonly'] = false;

        $this->withExceptionHandling()
            ->postJson('/nova-api/users', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ])
            ->assertStatus(201);
    }

    public function testFieldsAreNotStoredIfUserCantSeeThem()
    {
        $_SERVER['weight-field.canSee'] = false;
        $_SERVER['weight-field.readonly'] = false;

        $this->withExceptionHandling()
            ->postJson('/nova-api/users', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'weight'   => 190,
                'password' => 'password',
            ])
            ->assertStatus(201);

        $this->assertNull(User::first()->weight);
    }

    public function testReadonlyFieldsAreNotValidated()
    {
        $_SERVER['weight-field.canSee'] = true;
        $_SERVER['weight-field.readonly'] = true;

        $this->withExceptionHandling()
            ->postJson('/nova-api/users?editing=true&editMode=create', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ])
            ->assertStatus(201);
    }

    public function testReadonlyFieldsAreNotStored()
    {
        $_SERVER['weight-field.canSee'] = true;
        $_SERVER['weight-field.readonly'] = true;

        $this->withExceptionHandling()
            ->postJson('/nova-api/users?editing=true&editMode=create', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'weight'   => 190,
                'password' => 'password',
            ])
            ->assertStatus(201);

        $this->assertNull(User::first()->weight);
    }

    public function testResourceCanRedirectToDefaultUriOnCreate()
    {
        $response = $this->withoutExceptionHandling()
            ->postJson('/nova-api/users', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ]);

        $response->assertJson(['redirect' => '/resources/users/1']);
    }

    public function testResourceCanRedirectToCustomUriOnCreate()
    {
        $response = $this->withoutExceptionHandling()
            ->postJson('/nova-api/users-with-redirects', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ]);

        $response->assertJson(['redirect' => 'https://yahoo.com']);
    }

    public function testShouldStoreActionEventOnCorrectConnectionWhenCreating()
    {
        $this->setupActionEventsOnSeparateConnection();

        $response = $this->withoutExceptionHandling()
            ->postJson('/nova-api/users', [
                'name'     => 'Taylor Otwell',
                'email'    => 'taylor@laravel.com',
                'password' => 'password',
            ]);

        $response->assertStatus(201);

        $user = User::first();
        $this->assertEquals('Taylor Otwell', $user->name);
        $this->assertEquals('taylor@laravel.com', $user->email);

        $this->assertCount(0, DB::connection('sqlite')->table('action_events')->get());
        $this->assertCount(1, DB::connection('sqlite-custom')->table('action_events')->get());

        tap(Nova::actionEvent()->first(), function ($actionEvent) use ($user) {
            $this->assertEquals('Create', $actionEvent->first()->name);
            $this->assertEquals($user->id, $actionEvent->target_id);
            $this->assertEmpty($actionEvent->original);
            $this->assertSubset([
                'name'  => 'Taylor Otwell',
                'email' => 'taylor@laravel.com',
            ], $actionEvent->changes);
        });
    }

    public function tearDown(): void
    {
        unset($_SERVER['weight-field.readonly']);
        unset($_SERVER['weight-field.canSee']);

        parent::tearDown();
    }
}
