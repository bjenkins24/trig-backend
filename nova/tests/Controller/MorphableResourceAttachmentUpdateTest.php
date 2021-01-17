<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Tag;
use Laravel\Nova\Tests\IntegrationTest;

class MorphableResourceAttachmentUpdateTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanUpdateAttachedResources()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();
        $post->tags()->attach($tag, ['admin' => 'Y']);

        $this->assertEquals('Y', $post->fresh()->tags->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/update-attached/tags/'.$tag->id, [
                            'tags'            => $tag->id,
                            'admin'           => 'N',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, $post->fresh()->tags);
        $this->assertEquals($tag->id, $post->fresh()->tags->first()->id);
        $this->assertEquals('N', $post->fresh()->tags->first()->pivot->admin);
    }

    public function testCantUpdateAttachedResourcesIfNotRelatedResourceIsNotRelatable()
    {
        $post = factory(Post::class)->create();

        $tag = factory(Tag::class)->create();
        $tag2 = factory(Tag::class)->create();
        $tag3 = factory(Tag::class)->create();

        $post->tags()->attach($tag3, ['admin' => 'Y']);

        $this->assertEquals('Y', $post->fresh()->tags->first()->pivot->admin);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/update-attached/tags/'.$tag->id, [
                            'tags'            => $tag3->id,
                            'admin'           => 'N',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(422);
        $this->assertEquals('Y', $post->fresh()->tags->first()->pivot->admin);
    }

    public function test404IsReturnedIfResourceIsNotAttached()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();
        $post->tags()->attach($tag);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/update-attached/tags/100', [
                            'tags'            => $tag->id,
                            'admin'           => 'N',
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(404);
    }

    public function testPivotDataIsValidated()
    {
        $post = factory(Post::class)->create();
        $tag = factory(Tag::class)->create();
        $post->tags()->attach($tag);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/posts/'.$post->id.'/update-attached/tags/'.$tag->id, [
                            'tags'            => $tag->id,
                            'viaRelationship' => 'tags',
                        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['admin']);
    }
}
