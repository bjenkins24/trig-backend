<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class DetailAuthorizationTest extends DuskTestCase
{
    /**
     * @test
     */
    public function detailPageShouldNotBeAccessibleIfNotAuthorizedToView()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $post = PostFactory::new()->create();
        $user->shouldBlockFrom('post.view.'.$post->id);

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('posts', $post->id))
                    ->assertPathIs('/nova/403');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function cantNavigateToEditPageIfNotAuthorized()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $post = PostFactory::new()->create();
        $user->shouldBlockFrom('post.update.'.$post->id);

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('posts', $post->id))
                    ->assertMissing('@edit-resource-button');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceCantBeDeletedIfNotAuthorized()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $post = PostFactory::new()->create();
        $user->shouldBlockFrom('post.delete.'.$post->id);

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('posts', $post->id))
                    ->assertMissing('@open-delete-modal-button');

            $browser->blank();
        });
    }
}
