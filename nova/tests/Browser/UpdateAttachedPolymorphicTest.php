<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\Post;
use App\Models\User;
use Database\Factories\PostFactory;
use Database\Factories\TagFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\UpdateAttached;
use Laravel\Nova\Tests\DuskTestCase;

class UpdateAttachedPolymorphicTest extends DuskTestCase
{
    /**
     * @test
     */
    public function attachedResourceCanBeUpdated()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $tag = TagFactory::new()->create();
        $post->tags()->attach($tag, ['notes' => 'Test Notes']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UpdateAttached('posts', 1, 'tags', 1))
                    ->assertDisabled('@attachable-select')
                    ->assertInputValue('@notes', 'Test Notes')
                    ->type('@notes', 'Test Notes Updated')
                    ->update();

            $this->assertEquals('Test Notes Updated', Post::find(1)->tags->first()->pivot->notes);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function attachedSearchableResourceIsLocked()
    {
        $this->setupLaravel();

        $this->whileSearchable(function () {
            $post = PostFactory::new()->create();
            $tag = TagFactory::new()->create();
            $post->tags()->attach($tag, ['notes' => 'Test Notes']);

            $this->browse(function (Browser $browser) {
                $browser->loginAs(User::find(1))
                        ->visit(new UpdateAttached('posts', 1, 'tags', 1))
                        ->assertDisabled('@attachable-select')
                        ->assertInputValue('@notes', 'Test Notes')
                        ->type('@notes', 'Test Notes Updated')
                        ->update();

                $this->assertEquals('Test Notes Updated', Post::find(1)->tags->first()->pivot->notes);

                $browser->blank();
            });
        });
    }

    /**
     * @test
     */
    public function attachedResourceCanBeUpdatedAndCanContinueEditing()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $tag = TagFactory::new()->create();
        $post->tags()->attach($tag, ['notes' => 'Test Notes']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UpdateAttached('posts', 1, 'tags', 1))
                    ->type('@notes', 'Test Notes Updated')
                    ->updateAndContinueEditing();

            $browser->assertPathIs('/nova/resources/posts/1/edit-attached/tags/1');

            $this->assertEquals('Test Notes Updated', Post::find(1)->tags->first()->pivot->notes);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function validationErrorsAreDisplayed()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $tag = TagFactory::new()->create();
        $post->tags()->attach($tag, ['notes' => 'Test Notes']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UpdateAttached('posts', 1, 'tags', 1))
                    ->type('@notes', str_repeat('A', 30))
                    ->update()
                    ->assertSee('The notes may not be greater than 20 characters.');

            $this->assertEquals('Test Notes', Post::find(1)->tags->first()->pivot->notes);

            $browser->blank();
        });
    }
}
