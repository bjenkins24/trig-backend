<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\UserFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Index;
use Laravel\Nova\Testing\Browser\Pages\UserIndex;
use Laravel\Nova\Tests\DuskTestCase;

class IndexTest extends DuskTestCase
{
    /**
     * @test
     */
    public function resourceIndexCanBeViewed()
    {
        $this->setupLaravel();

        $users = User::find([1, 2, 3]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSeeResource(1)
                                ->assertSeeResource(2)
                                ->assertSeeResource(3)
                                ->assertSee('1-4 of 4');
                    })
                    ->assertTitle('Users | Nova Dusk Suite');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceIndexCantBeViewedOnInvalidResource()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Index('foobar'))
                    ->waitForText('404', 15)
                    ->assertPathIs('/nova/404');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canNavigateToCreateResourceScreen()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->click('@create-button');
                    })
                    ->waitForTextIn('h1', 'Create User', 25)
                    ->assertSee('Create & Add Another')
                    ->assertSee('Create User');

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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->click('@1-view-button');
                    })
                    ->waitForText('User Details', 25)
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->click('@1-edit-button');
                    })
                    ->waitForText('Update User', 25)
                    ->assertSee('Update User')
                    ->assertPathIs('/nova/resources/users/1/edit');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourcesCanBeSearched()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            // Search For Single User By ID...
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->searchFor('3')
                                ->assertDontSeeResource(1)
                                ->assertDontSeeResource(2)
                                ->assertSeeResource(3)
                                ->assertSee('1-1 of 1');
                    });

            // Search For Single User By Name...
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->searchFor('Taylor')
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
    public function resourcesSearchQueryWillResetOnRevisit()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            // Search For Single User By ID...
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->searchFor('3')
                                ->assertDontSeeResource(1)
                                ->assertDontSeeResource(2)
                                ->assertSeeResource(3)
                                ->assertDontSeeResource(4)
                                ->assertValue('@search', '3');
                    })
                    ->click('@users-resource-link')
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertValue('@search', '')
                                ->assertSeeResource(1)
                                ->assertSeeResource(2)
                                ->assertSeeResource(3)
                                ->assertSeeResource(4);
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testCorrectSelectAllMatchingCountIsDisplayed()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSee('1-4 of 4')
                                ->assertSelectAllMatchingCount(4)
                                ->click('')
                                ->searchFor('Taylor')
                                ->assertSelectAllMatchingCount(1)
                                ->assertSee('1-1 of 1');
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourcesCanBeSortedById()
    {
        $this->setupLaravel();

        UserFactory::new()->times(50)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSeeResource(50)
                                ->assertSeeResource(36)
                                ->assertDontSeeResource(25)
                                ->assertSee('1-25 of 54');

                        $browser->sortBy('id')
                                ->assertDontSeeResource(50)
                                ->assertDontSeeResource(26)
                                ->assertSeeResource(25)
                                ->assertSeeResource(1)
                                ->assertSee('1-25 of 54');
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourcesCanBeResortedByDifferentFieldDefaultToAscendingFirst()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSee('1-4 of 4')
                            ->assertSeeIn('table > tbody > tr:first-child', 'Laravel Nova');

                        $browser->sortBy('name')
                            ->assertSeeIn('table > tbody > tr:first-child', 'David Hemphill')
                            ->sortBy('name')
                            ->assertSeeIn('table > tbody > tr:first-child', 'Taylor Otwell')
                            ->sortBy('email')
                            ->assertSeeIn('table > tbody > tr:first-child', 'David Hemphill');
                    });

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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSeeResource(50)
                                ->assertSeeResource(30)
                                ->assertDontSeeResource(25)
                                ->assertSee('1-25 of 54');

                        $browser->nextPage()
                                ->assertDontSeeResource(50)
                                ->assertDontSeeResource(30)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(1)
                                ->assertSee('26-50 of 54');

                        $browser->previousPage()
                                ->assertSeeResource(50)
                                ->assertSeeResource(30)
                                ->assertDontSeeResource(25)
                                ->assertDontSeeResource(1)
                                ->assertSee('1-25 of 54');
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->setPerPage('50')
                                ->pause(1500)
                                ->assertSeeResource(50)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(1)
                                ->assertSee('1-50 of 54');
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->setPerPage('50')
                                ->pause(1500)
                                ->assertSeeResource(50)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(1)
                                ->assertSee('1-50 of 54');
                    })
                    ->refresh()
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSeeResource(50)
                                ->assertSeeResource(25)
                                ->assertDontSeeResource(1)
                                ->assertSee('1-50 of 54');
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->applyFilter('Select First', '1')
                            ->pause(1500)
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->assertSee('1-1 of 1')
                            ->applyFilter('Select First', '2')
                            ->pause(1500)
                            ->assertDontSeeResource(1)
                            ->assertSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->assertSee('1-1 of 1');
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->applyFilter('Select First', '1')
                            ->pause(1500)
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->assertSee('1-1 of 1')
                            ->applyFilter('Select First', '')
                            ->pause(1500)
                            ->assertSeeResource(1)
                            ->assertSeeResource(2)
                            ->assertSeeResource(3)
                            ->assertSee('1-4 of 4');
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->deleteResourceById(3)
                                ->assertSeeResource(1)
                                ->assertSeeResource(2)
                                ->assertDontSeeResource(3)
                                ->assertSee('1-3 of 3');
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->clickCheckboxForId(3)
                            ->clickCheckboxForId(2)
                            ->deleteSelected()
                            ->assertSeeResource(1)
                            ->assertDontSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->assertSee('1-2 of 2');
                    })
                    ->assertPathIs('/nova/resources/users');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canDeleteAllMatchingResources()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->searchFor('David')
                            ->selectAllMatching()
                            ->deleteSelected()
                            ->clearSearch()
                            ->assertSeeResource(1)
                            ->assertSeeResource(2)
                            ->assertDontSeeResource(3)
                            ->assertSee('1-3 of 3');
                    })
                    ->assertPathIs('/nova/resources/users');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function canRunActionsOnSelectedResources()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
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
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
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
}
