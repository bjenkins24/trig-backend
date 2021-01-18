<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\AddressFactory;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Detail;
use Laravel\Nova\Testing\Browser\Pages\Update;
use Laravel\Nova\Tests\DuskTestCase;

/**
 * @group external-network
 */
class PanelTest extends DuskTestCase
{
    /**
     * @test
     */
    public function fieldsCanBePlacedIntoPanels()
    {
        $this->setupLaravel();

        $address = AddressFactory::new()->create();

        $this->browse(function (Browser $browser) use ($address) {
            $browser->loginAs(User::find(1))
                    ->visit(new Detail('addresses', $address->id))
                    ->assertSee('More Address Details');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function fieldsCanBePlacedIntoEditPanels()
    {
        $this->setupLaravel();

        $address = AddressFactory::new()->create();

        $this->browse(function (Browser $browser) use ($address) {
            $browser->loginAs(User::find(1))
                ->visit(new Update('addresses', $address->id))
                ->assertSee('More Address Details');

            $browser->blank();
        });
    }
}
