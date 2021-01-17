<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Tests\Fixtures\Comment;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;

class FieldControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanRetrieveASingleField()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/field/email');

        $response->assertStatus(200);
        $this->assertInstanceOf(Text::class, $response->original);
        $this->assertEquals('email', $response->original->attribute);
    }

    public function test404ReturnedIfFieldDoesntExist()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/field/missing-field');

        $response->assertStatus(404);
    }

    public function testCanReturnCreationFields()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/creation-fields');

        $fields = collect($response->original['fields']);

        $response->assertStatus(200);
        $this->assertCount(0, $fields->where('attribute', 'id'));
        $this->assertCount(1, $fields->where('attribute', 'name'));
        $this->assertCount(1, $fields->where('attribute', 'email'));
        $this->assertCount(1, $fields->where('attribute', 'form'));
        $this->assertCount(0, $fields->where('attribute', 'update'));
        $this->assertCount(0, $fields->where('attribute', 'posts'));
    }

    public function testCantRetrieveCreationFieldsIfNotAuthorizedToCreateResource()
    {
        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.creatable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/creation-fields');

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.creatable']);

        $response->assertStatus(403);
    }

    public function testCanReturnUpdateFields()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/'.$user->id.'/update-fields')
                        ->assertOk();

        $fields = collect($response->original['fields']);
        $this->assertCount(0, $fields->where('attribute', 'id'));
        $this->assertCount(1, $fields->where('attribute', 'name'));
        $this->assertCount(1, $fields->where('attribute', 'email'));
        $this->assertCount(1, $fields->where('attribute', 'form'));
        $this->assertCount(1, $fields->where('attribute', 'update'));
        $this->assertCount(0, $fields->where('attribute', 'posts'));
    }

    public function testCreationFieldsDoNotContainDefaultValues()
    {
        $post = factory(Post::class)->create();
        $post->forceFill(['slug' => null]);
        $post->save();

        $response = $this->withExceptionHandling()
            ->getJson('/nova-api/posts/'.$post->id.'/update-fields');

        $response->assertJsonCount(3, 'fields');

        $this->assertNotEquals('default-slug', $response->decodeResponseJson()['fields'][3]['value']);
        $this->assertNull($response->decodeResponseJson()['fields'][3]['value']);
    }

    public function testCantRetrieveUpdateFieldsIfNotAuthorizedToUpdateResource()
    {
        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.updatable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/'.$user->id.'/update-fields');

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.updatable']);

        $response->assertStatus(403);
    }

    public function testCanReturnCreationPivotFields()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/6/creation-pivot-fields/roles');

        $fields = collect($response->original);

        $response->assertStatus(200);
        $this->assertCount(1, $fields->where('attribute', 'admin'));
        $this->assertCount(0, $fields->where('attribute', 'pivot-update'));
    }

    public function testCanReturnCreationPivotFieldsWithParentBelongsTo()
    {
        $response = $this->withoutExceptionHandling()
            ->get('/nova-api/roles/5/creation-pivot-fields/users?editing=true&editMode=attach');

        $fields = collect($response->original);

        $response->assertStatus(200);
        $this->assertCount(1, $fields->where('attribute', 'admin'));
        $this->assertCount(0, $fields->where('attribute', 'pivot-update'));
    }

    public function testCanReturnUpdatePivotFields()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/'.$user->id.'/update-pivot-fields/roles/'.$role->id.'?viaRelationship=roles');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['title']);
        $this->assertCount(1, collect($response->original['fields'])->where('attribute', 'pivot-update'));
    }

    public function testCanReturnViewablePropertyAuthorized()
    {
        Gate::policy(User::class, UserPolicy::class);

        $user = factory(User::class)->create();
        $post = factory(Post::class)->create(['user_id' => $user->id]);

        $response = $this->withExceptionHandling()
            ->get("/nova-api/posts/{$post->id}");

        $response->assertStatus(200);

        $fields = collect(json_decode(json_encode($response->original['resource']['fields']), true));

        $this->assertTrue($fields->firstWhere('attribute', 'user')['viewable']);
    }

    public function testCanReturnViewablePropertyDenied()
    {
        Gate::policy(User::class, UserPolicy::class);

        $user = factory(User::class)->create();
        $post = factory(Post::class)->create(['user_id' => $user->id]);

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.viewable'] = false;

        $response = $this->withExceptionHandling()
            ->get("/nova-api/posts/{$post->id}");

        $response->assertStatus(200);

        $fields = collect(json_decode(json_encode($response->original['resource']['fields']), true));

        unset($_SERVER['nova.user.viewable'], $_SERVER['nova.user.authorizable']);

        $this->assertFalse($fields->firstWhere('attribute', 'user')['viewable']);
    }

    public function testBelongsToFieldCanReturnViewablePropertyHidden()
    {
        $user = factory(User::class)->create();
        $post = factory(Post::class)->create(['user_id' => $user->id]);

        $_SERVER['nova.user.viewable-field'] = false;

        $response = $this->withExceptionHandling()
            ->get("/nova-api/posts/{$post->id}");

        $response->assertStatus(200);

        $fields = collect(json_decode(json_encode($response->original['resource']['fields']), true));

        unset($_SERVER['nova.user.viewable-field']);

        $this->assertFalse($fields->firstWhere('attribute', 'user')['viewable']);
    }

    public function testMorphToFieldCanReturnViewablePropertyHidden()
    {
        $parentComment = factory(Comment::class)->create();
        $comment = factory(Comment::class)->create(['commentable_id' => $parentComment->id]);

        $_SERVER['nova.comment.viewable-field'] = false;

        $response = $this->withoutExceptionHandling()
            ->get("/nova-api/comments/{$comment->commentable_id}")
            ->assertOk();

        $fields = collect(json_decode(json_encode($response->original['resource']['fields']), true));

        unset($_SERVER['nova.comment.viewable-field']);

        $this->assertFalse($fields->firstWhere('attribute', 'commentable')['viewable']);
    }
}
