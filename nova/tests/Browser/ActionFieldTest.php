<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\RoleFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Testing\Browser\Pages\UserIndex;
use Laravel\Nova\Tests\DuskTestCase;

class ActionFieldTest extends DuskTestCase
{
    /**
     * @test
     */
    public function actionsCanBeInstantlyDispatched()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->visit('/')->assertMissing('Nova');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function actionsCanReceiveAndUtilizeFieldInput()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $role = RoleFactory::new()->create();
        $user->roles()->attach($role);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($user = User::find(1))
                    ->visit(new Detail('users', 1))
                    ->waitFor('@roles-index-component', 25)
                    ->within(new IndexComponent('roles'), function ($browser) {
                        $browser->clickCheckboxForId(1)
                            ->runAction('update-pivot-notes', function ($browser) {
                                $browser->type('@notes', 'Custom Notes');
                            });
                    })->waitForText('The action ran successfully!', 25);

            $this->assertEquals('Custom Notes', $user->fresh()->roles->first()->pivot->notes);

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function actionsCanBeValidated()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $role = RoleFactory::new()->create();
        $user->roles()->attach($role);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($user = User::find(1))
                    ->visit(new Detail('users', 1))
                    ->waitFor('@roles-index-component', 25)
                    ->within(new IndexComponent('roles'), function ($browser) {
                        $browser->clickCheckboxForId(1)
                            ->runAction('update-required-pivot-notes')
                            ->elsewhere('.modal', function ($browser) {
                                $browser->assertSee('The Notes field is required.');
                            });
                    });

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function actionsCantBeExecutedWhenNotAuthorizedToRun()
    {
        $this->setupLaravel();

        User::whereIn('id', [1])->update(['active' => true]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new UserIndex())
                    ->waitFor('@users-index-component', 25)
                    ->within(new IndexComponent('users'), function ($browser) {
                        $browser->assertSeeIn('@1-row', 'Mark As Inactive')
                            ->assertDontSeeIn('@2-row', 'Mark As Inactive')
                            ->assertDontSeeIn('@3-row', 'Mark As Inactive')
                            ->runInlineAction(1, 'mark-as-inactive');
                    })->waitForText('Sorry! You are not authorized to perform this action.', 25);

            $this->assertEquals(1, User::find(1)->active);

            $browser->blank();
        });
    }
}
