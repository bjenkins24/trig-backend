<?php

namespace Laravel\Nova\Tests\Feature\Actions;

use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class ActionEventTest extends IntegrationTest
{
    public function testItCanSoftDeleteAModelWithoutLosingActionEventHistory()
    {
        $requestUser = factory(User::class)->create();

        $model = (new User())->forceFill([
            'name'     => 'Taylor Otwell',
            'email'    => 'taylor@laravel.com',
            'password' => bcrypt('password'),
        ]);
        $model->save();

        Nova::actionEvent()->forResourceCreate($requestUser, $model)->save();

        $this->assertSame(1, ActionEvent::where('actionable_type', User::class)->where('actionable_id', $model->id)->count());

        $model->name = 'Taylor Otwell';
        $model->save();

        Nova::actionEvent()->forResourceUpdate($requestUser, $model)->save();

        $this->assertSame(2, ActionEvent::where('actionable_type', User::class)->where('actionable_id', $model->id)->count());

        $response = $this->withExceptionHandling()
                        ->actingAs($requestUser)
                        ->deleteJson('/nova-api/users', [
                            'resources' => [$model->id],
                        ])
                        ->assertOk();

        $this->assertSame(3, ActionEvent::where('actionable_type', User::class)->where('actionable_id', $model->id)->count());

        $latestActionEvent = ActionEvent::where('actionable_id', $model->id)->latest('id')->first();
        $this->assertEquals('Delete', $latestActionEvent->name);
        $this->assertTrue($model->is($latestActionEvent->target));
    }

    public function testItCanForceDeleteASoftDeleteModelWillLoseActionEventHistory()
    {
        $requestUser = factory(User::class)->create();

        $model = (new User())->forceFill([
            'name'     => 'Taylor Otwell',
            'email'    => 'taylor@laravel.com',
            'password' => bcrypt('password'),
        ]);
        $model->save();

        Nova::actionEvent()->forResourceCreate($requestUser, $model)->save();

        $this->assertSame(1, ActionEvent::where('actionable_type', User::class)->where('actionable_id', $model->id)->count());

        $model->name = 'Taylor Otwell';
        $model->save();

        Nova::actionEvent()->forResourceUpdate($requestUser, $model)->save();

        $this->assertSame(2, ActionEvent::where('actionable_type', User::class)->where('actionable_id', $model->id)->count());

        $response = $this->withExceptionHandling()
                        ->actingAs($requestUser)
                        ->deleteJson('/nova-api/users/force', [
                            'resources' => [$model->id],
                        ])
                        ->assertOk();

        $this->assertSame(1, ActionEvent::where('actionable_type', User::class)->where('actionable_id', $model->id)->count());

        $latestActionEvent = ActionEvent::where('actionable_id', $model->id)->latest('id')->first();
        $this->assertEquals('Delete', $latestActionEvent->name);
    }

    public function testItBelongsToDefaultUserModel()
    {
        $action = new ActionEvent();

        $relation = $action->user();

        $this->assertSame(\Illuminate\Foundation\Auth\User::class, get_class($relation->getQuery()->getModel()));
    }

    /**
     * @environment-setup useCustomUserModelProvider
     */
    public function testItBelongsToCustomUserModel()
    {
        $action = new ActionEvent();

        $relation = $action->user();

        $this->assertSame(User::class, get_class($relation->getQuery()->getModel()));
    }

    public function useCustomUserModelProvider($app)
    {
        tap($app->make('config'), function ($config) {
            $config->set([
                'auth.providers.admin' => [
                    'driver' => 'eloquent',
                    'model'  => User::class,
                ],

                'auth.guards.admin' => [
                    'driver'   => 'session',
                    'provider' => 'admin',
                ],

                'nova.guard' => 'admin',
            ]);
        });
    }
}
