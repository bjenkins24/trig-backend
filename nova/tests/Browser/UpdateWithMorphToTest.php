<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\Post;
use App\Models\User;
use Database\Factories\CommentFactory;
use Database\Factories\LinkFactory;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Update;
use Laravel\Nova\Tests\DuskTestCase;

class UpdateWithMorphToTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceCanBeUpdatedToNewParent()
    {
        $this->setupLaravel();

        $comment = CommentFactory::new()->create();
        $post = PostFactory::new()->create();

        $this->browse(function (Browser $browser) use ($comment) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('comments', $comment->id))
                    ->searchAndSelectFirstRelation('commentable', 2)
                    ->update()
                    ->waitForText('The comment was updated');

            $this->assertCount(0, Post::find(1)->comments);
            $this->assertCount(1, Post::find(2)->comments);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function morphToFieldShouldHonorCustomPolymorphicType()
    {
        $this->setupLaravel();

        $link = LinkFactory::new()->create();
        $link->comments()->save($comment = CommentFactory::new()->create());

        $this->browse(function (Browser $browser) use ($comment, $link) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('comments', $comment->id))
                    ->assertEnabled('@commentable-type')
                    ->within('@commentable-type', function ($browser) {
                        $browser->assertSee('Link');
                    })
                    ->assertEnabled('@commentable-search-input')
                    ->within('@commentable-search-input', function ($browser) use ($link) {
                        $browser->assertSee($link->title);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function morphToFieldShouldIgnoreQueryParametersWhenEditing()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $post->comments()->save($comment = CommentFactory::new()->create());

        $this->browse(function (Browser $browser) use ($comment, $post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('comments', $comment->id, [
                        'viaResource'     => 'links',
                        'viaResourceId'   => 1,
                        'viaRelationship' => 'comments',
                    ]))
                    ->assertValue('@commentable-type', 'posts')
                    ->assertEnabled('@commentable-search-input')
                    ->within('@commentable-search-input', function ($browser) use ($post) {
                        $browser->assertSee($post->title);
                    });

            $browser->blank();
        });
    }
}
