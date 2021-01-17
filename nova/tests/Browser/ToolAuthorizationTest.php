<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Tests\DuskTestCase;

class ToolAuthorizationTest extends DuskTestCase
{
    /**
     * @test
     */
    public function testToolCanBeSeenIfAuthorizedToViewIt()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit('/nova')
                    ->pause(250)
                    ->assertSee('Sidebar Tool');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testToolCanCallItsOwnBackendRoutes()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit('/nova/sidebar-tool')
                    ->pause(250)
                    ->assertSee('Hello World');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testToolCantBeSeenIfNotAuthorizedToViewIt()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->shouldBlockFrom('sidebarTool');

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit('/nova')
                    ->pause(250)
                    ->assertDontSee('Sidebar Tool');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testToolCantBeNavigatedToIfNotAuthorizedToViewIt()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->shouldBlockFrom('sidebarTool');

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit('/nova/sidebar-tool')
                    ->pause(250)
                    ->assertSee('404')
                    ->assertDontSee('Sidebar Tool');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testResourceToolCanBeSeenIfAuthorizedToViewIt()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->assertSee('Resource Tool');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function testResourceToolCantBeSeenIfNotAuthorizedToViewIt()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->shouldBlockFrom('resourceTool');

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('users', 1))
                    ->assertDontSee('Resource Tool');

            $browser->blank();
        });
    }
}
