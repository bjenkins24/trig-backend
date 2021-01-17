<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Tag;
use Laravel\Nova\Tests\IntegrationTest;

class MorphableResourceAttachmentTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanAttachResources()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/attach-morphed/tags', [
                            'tags'            => $tag->id,
                            'admin'           => 'Y',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, $post->fresh()->tags);
        $this->assertEquals($tag->id, $post->fresh()->tags->first()->id);
        $this->assertEquals('Y', $post->fresh()->tags->first()->pivot->admin);
    }

    public function testCantAttachResourcesThatArentRelatable()
    {
        $post = factory(Post::class)->create();

        $tag = factory(Tag::class)->create();
        $tag2 = factory(Tag::class)->create();
        $tag3 = factory(Tag::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/attach-morphed/tags', [
                            'tags'            => $tag3->id,
                            'admin'           => 'Y',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(422);
        $this->assertCount(0, $post->fresh()->tags);
        $this->assertFalse(isset($_SERVER['nova.post.relatableTags']));
    }

    public function testResourceMaySpecifyCustomRelatableQueryCustomizer()
    {
        $post = factory(Post::class)->create();

        $tag = factory(Tag::class)->create();
        $tag2 = factory(Tag::class)->create();
        $tag3 = factory(Tag::class)->create();

        $_SERVER['nova.post.useCustomRelatableTags'] = true;
        unset($_SERVER['nova.post.relatableTags']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/attach-morphed/tags', [
                            'tags'            => $tag3->id,
                            'admin'           => 'Y',
                            'viaRelationship' => 'tags',
                        ]);

        unset($_SERVER['nova.post.useCustomRelatableTags']);

        $this->assertNotNull($_SERVER['nova.post.relatableTags']);
        $response->assertStatus(422);
        $this->assertCount(0, $post->fresh()->tags);

        unset($_SERVER['nova.post.relatableTags']);
    }

    public function testAttachedResourceMustExist()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/attach/tags', [
                            'tags'            => 100,
                            'admin'           => 'Y',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tags']);

        $this->assertCount(0, $post->fresh()->tags);
    }

    public function testAttachedResourceMustNotAlreadyBeAttached()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();
        $post->tags()->attach($tag);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/attach/tags', [
                            'tags'            => $tag->id,
                            'admin'           => 'Y',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tags']);

        $this->assertCount(1, $post->fresh()->tags);
    }

    public function testPivotDataIsValidated()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/attach/tags', [
                            'tags'            => $tag->id,
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['admin']);
    }
}
