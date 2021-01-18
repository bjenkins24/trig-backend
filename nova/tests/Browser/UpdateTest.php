<?php

namespace Laravel\Nova\Tests\Browser;

use App\Models\User;
use Database\Factories\PostFactory;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Laravel\Nova\Testing\Browser\Pages\Update;
use Laravel\Nova\Tests\DuskTestCase;

class UpdateTest extends DuskTestCase
{
    /**
     * @test
     */
    public function cantViewUpdatePageIfNotAuthorizedToUpdate()
    {
        $this->setupLaravel();

        $post = PostFactory::new()->create();
        $post2 = PostFactory::new()->create();

        $user = User::find(1);
        $user->shouldBlockFrom('post.update.'.$post->id);

        $this->browse(function (Browser $browser) use ($user, $post, $post2) {
            $browser->loginAs($user)
                    ->visit(new Update('posts', $post->id))
                    ->assertPathIs('/nova/403');

            $browser->visit(new Update('posts', $post2->id))
                    ->assertPathIsNot('/nova/403');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceCanBeUpdated()
    {
        $this->setupLaravel();

        $user = User::find(1);
        $user->name = 'Taylor';
        $user->save();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit(new Update('users', 1))
                    ->type('@name', 'Taylor Otwell upDATED')
                    ->type('@password', 'secret')
                    ->update();

            $user = User::find(1);

            $this->assertEquals('Taylor Otwell Updated', $user->name);
            $this->assertTrue(Hash::check('secret', $user->password));

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function validationErrorsAreDisplayed()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('users', 1))
                    ->type('@name', ' ')
                    ->update()
                    ->waitForText('There was a problem submitting the form.', 15)
                    ->assertSee('The Name field is required.');

            $browser->blank();
        });
    }

    /**
     * @test
     */
    public function resourceCanBeUpdatedAndUserCanContinueEditing()
    {
        $this->setupLaravel();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit(new Update('users', 1))
                    ->type('@name', 'Taylor Otwell Updated')
                    ->type('@password', 'secret')
                    ->updateAndContinueEditing();

            $user = User::find(1);

            $browser->assertPathIs('/nova/resources/users/'.$user->id.'/edit');

            $this->assertEquals('Taylor Otwell Updated', $user->name);
            $this->assertTrue(Hash::check('secret', $user->password));

            $browser->blank();
        });
    }
}
