<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\RoleFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class PivotActionTest extends DuskTestCase
{
    /**
     * @test
     */
    public function pivotTablesCanBeReferredToUsingACustomName()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $role = RoleFactory::new()->create();
        $user->roles()->attach($role);

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->pause(1500)
                    ->within(new IndexComponent('roles'), function ($browser) {
                        $browser->clickCheckboxForId(1)
                                ->openActionSelector()
                                ->within('@action-select', function ($browser) {
                                    $label = $browser->attribute('optgroup.pivot-option-group', 'label');
                                    $this->assertEquals('Role Assignment', $label);
                                });
                    });
        });
    }

    /**
     * @test
     */
    public function actionsCanBeExecutedAgainstPivotRows()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $role = RoleFactory::new()->create();
        $user->roles()->attach($role);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->pause(1500)
                    ->within(new IndexComponent('roles'), function ($browser) {
                        $browser->clickCheckboxForId(1)
                                ->runAction('update-pivot-notes');
                    });

            $browser->waitForText('The action ran successfully!', 25);

            $this->assertEquals('Pivot Action Notes', $user->fresh()->roles()->first()->pivot->notes);

            $browser->blank();
        });
    }
}
