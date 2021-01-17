<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Tests\Fixtures\Comment;
use Laravel\Nova\Tests\Fixtures\CommentPolicy;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\IntegrationTest;

class MorphableResourceCreationTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanCreateResources()
    {
        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/comments', [
                            'commentable'      => $post->id,
                            'commentable_type' => 'posts',
                            'author'           => 1,
                            'body'             => 'Comment Body',
                        ]);

        $response->assertStatus(201);
        $this->assertEquals(Comment::first()->commentable->title, $post->title);
    }

    public function testCantCreateResourcesIfNotAuthorizedToCreate()
    {
        $_SERVER['nova.comment.authorizable'] = true;
        $_SERVER['nova.comment.creatable'] = false;

        Gate::policy(Comment::class, CommentPolicy::class);

        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/comments', [
                            'commentable'      => $post->id,
                            'commentable_type' => 'posts',
                            'author'           => 1,
                            'body'             => 'Comment Body',
                        ]);

        unset($_SERVER['nova.comment.authorizable']);
        unset($_SERVER['nova.comment.creatable']);

        $response->assertStatus(403);
    }

    public function testCantCreateResourcesIfParentResourceIsNotRelatable()
    {
        $post = factory(Post::class)->create();
        $post2 = factory(Post::class)->create();
        $post3 = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/comments', [
                            'commentable'      => $post3->id,
                            'commentable_type' => 'posts',
                            'author'           => 1,
                            'body'             => 'Comment Body',
                        ]);

        $response->assertStatus(422);
        $this->assertFalse(isset($_SERVER['nova.post.relatablePosts']));
    }

    public function testResourceMaySpecifyCustomRelatableQueryCustomizer()
    {
        $post = factory(Post::class)->create();
        $post2 = factory(Post::class)->create();
        $post3 = factory(Post::class)->create();

        $_SERVER['nova.comment.useCustomRelatablePosts'] = true;
        unset($_SERVER['nova.post.relatablePosts']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/comments', [
                            'commentable'      => $post3->id,
                            'commentable_type' => 'posts',
                            'author'           => 1,
                            'body'             => 'Comment Body',
                        ]);

        unset($_SERVER['nova.comment.useCustomRelatablePosts']);

        $this->assertNotNull($_SERVER['nova.comment.relatablePosts']);
        $response->assertStatus(422);

        unset($_SERVER['nova.comment.relatablePosts']);
    }

    public function testMorphableResourceMustExist()
    {
        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/comments', [
                            'commentable'      => 100,
                            'commentable_type' => 'posts',
                            'body'             => 'Comment Body',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['commentable']);
    }

    public function testMorphableTypeMustBeValid()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/comments', [
                            'commentable'      => 100,
                            'commentable_type' => 'videos',
                            'body'             => 'Comment Body',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['commentable_type']);
    }
}
