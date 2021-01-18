<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\DockFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Create;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class CreateWithBelongsToTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceCanBeCreated()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Create('posts'))
                    ->select('@user', 1)
                    ->type('@title', 'Test Post')
                    ->type('@body', 'Test Post Body')
                    ->create();

            $user = User::find(1);
            $post = $user->posts->first();
            $this->assertEquals('Test Post', $post->title);
            $this->assertEquals('Test Post Body', $post->body);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function parentResourceShouldBeLockedWhenCreatingViaParentsDetailPage()
    {
        $this->setupLaravel();

        $user = User::find(1);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->within(new IndexComponent('posts'), function ($browser) {
                        $browser->click('@create-button');
                    })
                    ->on(new Create('posts'))
                    ->pause(175)
                    ->assertDisabled('@user')
                    ->type('@title', 'Test Post')
                    ->type('@body', 'Test Post Body')
                    ->create();

            $user = User::find(1);
            $post = $user->posts->first();
            $this->assertEquals('Test Post', $post->title);
            $this->assertEquals('Test Post Body', $post->body);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function searchableResourceCanBeCreated()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();

        $this->browse(function (Browser $browser) use ($dock) {
            $browser->loginAs(User::find(1))
                    ->visit(new Create('ships'))
                    ->searchAndSelectFirstRelation('docks', '1')
                    ->type('@name', 'Test Ship')
                    ->create();

            $this->assertCount(1, $dock->fresh()->ships);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function searchableParentResourceShouldBeLockedWhenCreatingViaParentsDetailPage()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();

        $this->browse(function (Browser $browser) use ($dock) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->waitFor('@ships-index-component', 25)
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->click('@create-button');
                    })
                    ->on(new Create('ships'))
                    ->assertDisabled('@dock')
                    ->type('@name', 'Test Ship')
                    ->create();

            $this->assertCount(1, $dock->fresh()->ships);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function belongsToFieldShouldHonorCustomLabelsOnCreate()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Create('invoice-items'))
                    ->assertSee('Client Invoice');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function belongsToFieldShouldHonorQueryParametersOnCreate()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Create('posts', [
                        'viaResource'     => 'users',
                        'viaResourceId'   => 1,
                        'viaRelationship' => 'posts',
                    ]))
                    ->assertValue('@user', 1);

            $browser->blank();
        });
    }
}
