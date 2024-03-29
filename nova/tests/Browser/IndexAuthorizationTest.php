<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Index;
use Laravel\Nova\Tests\DuskTestCase;

class IndexAuthorizationTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceIndexCanBeTotallyBlockedViaViewAny()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();

        $user = User::find(1);
        $user->shouldBlockFrom('post.viewAny');

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Index('posts'))
                    ->pause(250)
                    ->assertPathIs('/nova/403');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function shouldntSeeEditButtonIfBlockedFromUpdating()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $post2 = PostFactory::new()->create();

        $user = User::find(1);
        $user->shouldBlockFrom('post.update.'.$post->id);

        $this->browse(function (Browser $browser) use ($post, $post2) {
            $browser->loginAs(User::find(1))
                    ->visit(new Index('posts'))
                    ->waitFor('@posts-index-component', 25)
                    ->within(new IndexComponent('posts'), function ($browser) use ($post, $post2) {
                        $browser->assertMissing('@'.$post->id.'-edit-button');
                        $browser->assertVisible('@'.$post2->id.'-edit-button');
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function shouldntSeeDeleteButtonIfBlockedFromDeleting()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $post2 = PostFactory::new()->create();

        $user = User::find(1);
        $user->shouldBlockFrom('post.delete.'.$post->id);

        $this->browse(function (Browser $browser) use ($post, $post2) {
            $browser->loginAs(User::find(1))
                    ->visit(new Index('posts'))
                    ->waitFor('@posts-index-component', 25)
                    ->within(new IndexComponent('posts'), function ($browser) use ($post, $post2) {
                        $browser->assertMissing('@'.$post->id.'-delete-button');
                        $browser->assertVisible('@'.$post2->id.'-delete-button');
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canDeleteResourcesUsingCheckboxesOnlyIfAuthorizedToDeleteThem()
    {
        $this->setupLaravel();

        PostFactory::new()->times(3)->create();

        $user = User::find(1);
        $user->shouldBlockFrom('post.delete.1');

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Index('posts'))
                    ->waitFor('@posts-index-component', 25)
                    ->within(new IndexComponent('posts'), function ($browser) {
                        $browser->clickCheckboxForId(3)
                            ->clickCheckboxForId(2)
                            ->clickCheckboxForId(1)
                            ->deleteSelected()
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canDeleteAllMatchingResourcesOnlyIfAuthorizedToDeleteThem()
    {
        $this->setupLaravel();

        PostFactory::new()->times(3)->create();

        $user = User::find(1);
        $user->shouldBlockFrom('post.delete.1');

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Index('posts'))
                    ->waitFor('@posts-index-component', 25)
                    ->within(new IndexComponent('posts'), function ($browser) {
                        $browser->selectAllMatching()
                            ->deleteSelected()
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3);
                    });

            $browser->blank();
        });
    }
}
