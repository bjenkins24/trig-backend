<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\UserFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\LensComponent;
use Laravel\Nova\Testing\Browser\Pages\Lens;
use Laravel\Nova\Tests\DuskTestCase;

class LensTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceLensCanBeViewed()
    {
        $this->setupLaravel();

        $users = User::find([1, 2, 3]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->assertSeeResource(1)
                                ->assertSeeResource(2)
                                ->assertSeeResource(3);
                    })
                    ->assertTitle('Passthrough Lens | Nova Dusk Suite');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canNavigateToDetailScreen()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->click('@1-view-button');
                    })
                    ->waitForTextIn('h1', 'User Details', 25)
                    ->assertSee('User Details')
                    ->assertPathIs('/nova/resources/users/1');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canNavigateToEditScreen()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->click('@1-edit-button');
                    })
                    ->pause(1000)
                    ->assertSee('Update User')
                    ->assertPathIs('/nova/resources/users/1/edit');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    // public function test_correct_select_all_matching_count_is_displayed()
    // {
    //     $this->setupLaravel();

    //     $this->browse(function (Browser $browser) {
    //         $browser->loginAs(User::find(1))
    //                 ->visit(new Lens('users', 'passthrough-lens'))
    //                 ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
    //                     $browser->assertSelectAllMatchingCount(3)
    //                             ->click('')
    //                             ->applyFilter('Select First', '1')
    //                             ->assertSelectAllMatchingCount(1);
    //                 });
    //     });
    // }

    /**
     * @test
     */
    public function resourcesCanBeSortedById()
    {
        $this->setupLaravel();

        UserFactory::new()->times(50)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->assertSeeResource(1)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(26);

                        $browser->sortBy('id')
                                ->sortBy('id')
                                ->assertSeeResource(50)
                                ->assertSeeResource(30)
                                ->assertDontSeeResource(26)
                                ->assertDontSeeResource(1);
                    })->blank();

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourcesCanBePaginated()
    {
        $this->setupLaravel();

        UserFactory::new()->times(50)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->assertSeeResource(1)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(26);

                        $browser->nextPage()
                                ->assertDontSeeResource(1)
                                ->assertDontSeeResource(25)
                                ->assertSeeResource(26)
                                ->assertSeeResource(50);

                        $browser->previousPage()
                                ->assertSeeResource(1)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(26)
                                ->assertDontSeeResource(50);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function numberOfResourcesDisplayedPerPageCanBeChanged()
    {
        $this->setupLaravel();

        UserFactory::new()->times(50)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->setPerPage('50')
                                ->pause(1500)
                                ->assertSeeResource(50)
                                ->assertSeeResource(25)
                                ->assertSeeResource(1);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function numberOfResourcesDisplayedPerPageIsSavedInQueryParams()
    {
        $this->setupLaravel();

        UserFactory::new()->times(50)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->setPerPage('50')
                                ->pause(1500)
                                ->assertSeeResource(50)
                                ->assertSeeResource(25)
                                ->assertSeeResource(1);
                    })
                    ->refresh()
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->assertSeeResource(50)
                                ->assertSeeResource(25)
                                ->assertSeeResource(1);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testFiltersCanBeAppliedToResources()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->applyFilter('Select First', '1')
                            ->pause(1500)
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->applyFilter('Select First', '2')
                            ->pause(1500)
                            ->assertDontSeeResource(1)
                            ->assertSeeResource(2)
                            ->assertDontSeeResource(3);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testFiltersCanBeDeselected()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->applyFilter('Select First', '1')
                            ->pause(1500)
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->applyFilter('Select First', '')
                            ->pause(1500)
                            ->assertSeeResource(1)
                            ->assertSeeResource(2)
                            ->assertSeeResource(3);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canDeleteAResourceViaResourceTableRowDeleteIcon()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->deleteResourceById(3)
                                ->assertSeeResource(1)
                                ->assertSeeResource(2)
                                ->assertDontSeeResource(3);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canDeleteResourcesUsingCheckboxes()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->clickCheckboxForId(3)
                            ->clickCheckboxForId(2)
                            ->pause(175)
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
    // public function can_delete_all_matching_resources()
    // {
    //     $this->setupLaravel();

    //     $this->browse(function (Browser $browser) {
    //         $browser->loginAs(User::find(1))
    //                 ->visit(new Lens('users', 'passthrough-lens'))
    //                 ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
    //                     $browser->applyFilter('Select First', '3')
    //                         ->selectAllMatching()
    //                         ->deleteSelected()
    //                         ->applyFilter('Select First', '')
    //                         ->assertSeeResource(1)
    //                         ->assertSeeResource(2)
    //                         ->assertDontSeeResource(3);
    //                 });
    //     });
    // }

    /**
     * @test
     */
    public function canRunActionsOnSelectedResources()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->clickCheckboxForId(3)
                            ->clickCheckboxForId(2)
                            ->runAction('mark-as-active');
                    });

            $this->assertEquals(0, User::find(1)->active);
            $this->assertEquals(1, User::find(2)->active);
            $this->assertEquals(1, User::find(3)->active);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canRunTableRowActionsOnSelectedResources()
    {
        $this->setupLaravel();

        User::whereIn('id', [2, 3, 4])->update(['active' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Lens('users', 'passthrough-lens'))
                    ->waitFor('@passthrough-lens-lens-component', 25)
                    ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
                        $browser->assertDontSeeIn('@1-row', 'Mark As Inactive')
                            ->assertSeeIn('@2-row', 'Mark As Inactive')
                            ->runInlineAction(2, 'mark-as-inactive');
                    });

            $this->assertEquals(0, User::find(1)->active);
            $this->assertEquals(0, User::find(2)->active);
            $this->assertEquals(1, User::find(3)->active);

            $browser->blank();
        });
    }

    /*
     * @test
     */
    // public function can_run_actions_on_all_matching_resources()
    // {
    //     $this->setupLaravel();

    //     $this->browse(function (Browser $browser) {
    //         $browser->loginAs(User::find(1))
    //                 ->visit(new Lens('users', 'passthrough-lens'))
    //                 ->within(new LensComponent('users', 'passthrough-lens'), function ($browser) {
    //                     $browser->applyFilter('Select First', '2');

    //                     $browser->selectAllMatching()
    //                             ->runAction('mark-as-active');
    //                 });
    //     });

    //     $this->assertEquals(0, User::find(1)->active);
    //     $this->assertEquals(1, User::find(2)->active);
    //     $this->assertEquals(0, User::find(3)->active);
    // }
}
