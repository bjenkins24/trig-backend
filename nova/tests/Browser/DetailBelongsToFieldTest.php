<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\InvoiceItemFactory;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\DetailComponent;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class DetailBelongsToFieldTest extends DuskTestCase
{
    /**
     * @test
     */
    public function belongsToFieldNavigatesToParentResourceWhenClicked()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->posts()->save($post = PostFactory::new()->create());

        $this->browse(function (Browser $browser) use ($user, $post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('posts', $post->id))
                    ->within(new DetailComponent('posts', $post->id), function ($browser) use ($user) {
                        $browser->clickLink($user->name);
                    })
                    ->waitForTextIn('h1', 'User Details', 25)
                    ->assertPathIs('/nova/resources/users/'.$user->id);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function belongsToFieldShouldHonorCustomLabels()
    {
        $this->setupLaravel();

        InvoiceItemFactory::new()->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('invoice-items', 1))
                    ->assertSee('Client Invoice');

            $browser->blank();
        });
    }
}
