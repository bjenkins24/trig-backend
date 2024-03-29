<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\Dock;
use App\Models\User;
use Database\Factories\DockFactory;
use Database\Factories\ShipFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Testing\Browser\Pages\Update;
use Laravel\Nova\Tests\DuskTestCase;

class SoftDeletingDetailTest extends DuskTestCase
{
    /**
     * @test
     */
    public function canViewResourceAttributes()
    {
        $this->setupLaravel();

        DockFactory::new()->create(['name' => 'Test Dock']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->assertSee('Test Dock');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canRunActionsOnResource()
    {
        $this->setupLaravel();

        DockFactory::new()->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->runAction('mark-as-active');

            $this->assertEquals(1, Dock::find(1)->active);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canNavigateToEditPage()
    {
        $this->setupLaravel();

        DockFactory::new()->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->click('@edit-resource-button')
                    ->waitForTextIn('h1', 'Update Dock', 25)
                    ->assertPathIs('/nova/resources/docks/1/edit');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceCanBeDeleted()
    {
        $this->setupLaravel();

        DockFactory::new()->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->delete();

            $browser->assertPathIs('/nova/resources/docks/1');

            $this->assertEquals(1, Dock::onlyTrashed()->count());

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceCanBeRestored()
    {
        $this->setupLaravel();

        DockFactory::new()->create(['deleted_at' => now()]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->restore()
                    ->waitForText('The dock was restored!', 25)
                    ->assertPathIs('/nova/resources/docks/1');

            $this->assertEquals(1, Dock::count());

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceCanBeEditedOnSoftDeleted()
    {
        $this->setupLaravel();

        DockFactory::new()->create([
            'name'       => 'hello',
            'deleted_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('docks', 1))
                    ->type('@name', 'world')
                    ->update()
                    ->waitForText('The dock was updated!', 25)
                    ->assertPathIs('/nova/resources/docks/1');

            $browser->blank();

            $dock = Dock::onlyTrashed()->find(1);
            $this->assertEquals('world', $dock->name);
        });
    }

    /**
     * @test
     */
    public function resourceCanRunActionOnSoftDeleted()
    {
        $this->setupLaravel();

        DockFactory::new()->create([
            'name'       => 'hello',
            'deleted_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->runAction('mark-as-active')
                    ->waitForText('The action ran successfully!', 25);

            $browser->blank();

            $dock = Dock::onlyTrashed()->find(1);
            $this->assertEquals(true, $dock->active);
        });
    }

    /**
     * @test
     */
    public function resourceCanBeForceDeleted()
    {
        $this->setupLaravel();

        DockFactory::new()->create(['deleted_at' => now()]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->forceDelete();

            $browser->assertPathIs('/nova/resources/docks');

            $this->assertEquals(0, Dock::count());

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function relationshipsCanBeSearched()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();
        $dock->ships()->save($ship = ShipFactory::new()->create());

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->assertSeeResource(1)
                                ->searchFor('No Matching Ships')
                                ->assertDontSeeResource(1);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function softDeletingResourcesCanBeManipulatedFromTheirChildIndex()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();
        $dock->ships()->save($ship = ShipFactory::new()->create());

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->withTrashed();

                        $browser->assertSeeResource(1)
                                ->deleteResourceById(1)
                                ->restoreResourceById(1)
                                ->assertSeeResource(1);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canNavigateToCreateRelationshipScreen()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->click('@create-button')
                                ->assertPathIs('/nova/resources/ships/new')
                                ->assertQueryStringHas('viaResource', 'docks')
                                ->assertQueryStringHas('viaResourceId', '1')
                                ->assertQueryStringHas('viaRelationship', 'ships');
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function relationsCanBePaginated()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();
        $dock->ships()->saveMany(ShipFactory::new()->times(10)->create());

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->assertSeeResource(10)
                                ->assertDontSeeResource(1)
                                ->nextPage()
                                ->assertDontSeeResource(10)
                                ->assertSeeResource(1)
                                ->previousPage()
                                ->assertSeeResource(10)
                                ->assertDontSeeResource(1);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function relationsCanBeSorted()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();
        $dock->ships()->saveMany(ShipFactory::new()->times(10)->create());

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->assertSeeResource(10)
                                ->assertSeeResource(6)
                                ->assertDontSeeResource(1)
                                ->sortBy('id')
                                ->assertDontSeeResource(10)
                                ->assertDontSeeResource(6)
                                ->assertSeeResource(5)
                                ->assertSeeResource(1);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function actionsOnAllMatchingRelationsShouldBeScopedToTheRelation()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();
        $dock->ships()->save($ship = ShipFactory::new()->create());

        $dock2 = DockFactory::new()->create();
        $dock2->ships()->save($ship2 = ShipFactory::new()->create());

        $this->browse(function (Browser $browser) use ($ship, $ship2) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->selectAllMatching()
                                ->runAction('mark-as-active');
                    });

            $this->assertEquals(1, $ship->fresh()->active);
            $this->assertEquals(0, $ship2->fresh()->active);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function deletingAllMatchingRelationsIsScopedToTheRelationships()
    {
        $this->setupLaravel();

        $dock = DockFactory::new()->create();
        $dock->ships()->save($ship = ShipFactory::new()->create());

        $dock2 = DockFactory::new()->create();
        $dock2->ships()->save($ship2 = ShipFactory::new()->create());

        $this->browse(function (Browser $browser) use ($ship, $ship2) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('docks', 1))
                    ->within(new IndexComponent('ships'), function ($browser) {
                        $browser->selectAllMatching()
                                ->deleteSelected();
                    });

            $this->assertNotNull($ship->fresh()->deleted_at);
            $this->assertNull($ship2->fresh()->deleted_at);

            $browser->blank();
        });
    }
}
