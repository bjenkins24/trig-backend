<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Components\IndexComponent;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class QueuedActionTest extends DuskTestCase
{
    /**
     * @test
     */
    public function queuedActionStatusIsDisplayedInActionEventsList()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->runAction('sleep')
                    ->waitFor('[dusk="action-events-index-component"] table', 60)
                    ->within(new IndexComponent('action-events'), function ($browser) {
                        $browser->scrollIntoView('')
                                ->assertSee('Sleep')
                                ->assertSee('Finished');
                    });
        });
    }
}
