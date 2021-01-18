<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Update;
use Laravel\Nova\Tests\DuskTestCase;

class UpdateAuthorizationTest extends DuskTestCase
{
    /**
     * @test
     */
    public function updatePageShouldNotBeAccessibleIfNotAuthorizedToView()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $post = PostFactory::new()->create();
        $user->shouldBlockFrom('post.update.'.$post->id);

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('posts', $post->id))
                    ->assertPathIs('/nova/403');

            $browser->blank();
        });
    }
}
