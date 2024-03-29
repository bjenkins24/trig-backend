<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\PostFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Create;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class CreateWithMorphToTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceCanBeCreated()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Create('comments'))
                    ->select('@commentable-type', 'posts')
                    ->pause(500)
                    ->searchAndSelectFirstRelation('commentable', 1)
                    ->type('@body', 'Test Comment')
                    ->create();

            $browser->assertPathIs('/nova/resources/comments/1');

            $this->assertCount(1, $post->fresh()->comments);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function searchableResourceCanBeCreated()
    {
        $this->setupLaravel();

        $this->whileSearchable(function () {
            $post = PostFactory::new()->create();

            $this->browse(function (Browser $browser) use ($post) {
                $browser->loginAs(User::find(1))
                        ->visit(new Create('comments'))
                        ->select('@commentable-type', 'posts')
                        ->pause(500)
                        ->searchAndSelectFirstRelation('commentable', 1)
                        ->type('@body', 'Test Comment')
                        ->create();

                $browser->assertPathIs('/nova/resources/comments/1');

                $this->assertCount(1, $post->fresh()->comments);

                $browser->blank();
            });
        });
    }

    /**
     * @test
     */
    public function nonSearchableResourceCanBeCreatedViaParentResource()
    {
        $this->resource_can_be_created_via_parent_resource();
    }

    /**
     * @test
     */
    public function searchableResourceCanBeCreatedViaParentResource()
    {
        $this->whileSearchable(function () {
            $this->resource_can_be_created_via_parent_resource();
        });
    }

    protected function resource_can_be_created_via_parent_resource()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('posts', $post->id))
                    ->waitFor('@comments-index-component', 25)
                    ->within(new IndexComponent('comments'), function ($browser) {
                        $browser->click('@create-button');
                    })
                    ->on(new Create('comments'))
                    ->assertDisabled('@commentable-type')
                    ->assertDisabled('@commentable-select')
                    ->type('@body', 'Test Comment')
                    ->create();

            $browser->assertPathIs('/nova/resources/comments/1');

            $this->assertCount(1, $post->fresh()->comments);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function morphToFieldShouldHonorCustomLabels()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Create('comments'))
                    ->assertSee('User Post')
                    ->assertSee('User Video');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function morphToFieldShouldHonorQueryParametersOnCreate()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();

        $this->browse(function (Browser $browser) use ($post) {
            $browser->loginAs(User::find(1))
                ->visit(new Create('comments', [
                    'viaResource'     => 'posts',
                    'viaResourceId'   => $post->id,
                    'viaRelationship' => 'comments',
                ]))
                ->assertValue('@commentable-type', 'posts')
                ->assertValue('@commentable-select', $post->id);

            $browser->blank();
        });
    }
}
