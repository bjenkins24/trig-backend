<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Update;
use Laravel\Nova\Tests\DuskTestCase;

class UpdateWithBelongsToTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceCanBeUpdatedToNewParent()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->posts()->save($post = PostFactory::new()->make());

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('posts', $post->id))
                    ->select('@user', 2)
                    ->update()
                    ->waitForText('The user post was updated');

            $this->assertCount(0, User::find(1)->posts);
            $this->assertCount(1, User::find(2)->posts);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function belongsToFieldShouldIgnoreQueryParametersWhenEditing()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->posts()->save($post = PostFactory::new()->make());

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                ->visit(new Update('posts', $post->id, [
                    'viaResource'     => 'users',
                    'viaResourceId'   => 2,
                    'viaRelationship' => 'posts',
                ]))
                ->assertValue('@user', 1); // not 2

            $browser->blank();
        });
    }
}
