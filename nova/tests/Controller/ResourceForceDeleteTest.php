<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\IdFilter;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;

class ResourceForceDeleteTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanForceDeleteResources()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users/force', [
                            'resources' => [$user->id, $user2->id],
                        ]);

        $response->assertStatus(200);

        $this->assertCount(0, User::all());

        $this->assertCount(2, ActionEvent::all());
        $this->assertEquals('Delete', ActionEvent::first()->name);
        $this->assertEquals($user->id, ActionEvent::where('actionable_id', $user->id)->first()->target_id);
    }

    public function testCanForceDeleteResourcesViaSearch()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users/force?search='.$user->email, [
                            'resources' => 'all',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, User::all());

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Delete', ActionEvent::first()->name);
        $this->assertEquals($user->id, ActionEvent::where('actionable_id', $user->id)->first()->target_id);
    }

    public function testCanDestroyResourcesViaFilters()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        $filters = base64_encode(json_encode([
            [
                'class' => IdFilter::class,
                'value' => 1,
            ],
        ]));

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users/force?filters='.$filters, [
                            'resources' => 'all',
                        ]);

        $response->assertStatus(200);

        $this->assertCount(1, User::all());

        $this->assertCount(1, ActionEvent::all());
        $this->assertEquals('Delete', ActionEvent::first()->name);
        $this->assertEquals($user->id, ActionEvent::where('actionable_id', $user->id)->first()->target_id);
    }

    public function testCantForceDeleteResourcesNotAuthorizedToForceDelete()
    {
        $user = factory(User::class)->create();

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.forceDeletable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users/force', [
                            'resources' => [$user->id],
                        ]);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.forceDeletable']);

        $response->assertStatus(200);
        $this->assertCount(1, User::all());
        $this->assertCount(0, ActionEvent::all());
    }

    public function testShouldStoreActionEventOnCorrectConnectionWhenForceDeleting()
    {
        $this->setupActionEventsOnSeparateConnection();

        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        $response = $this->withExceptionHandling()
            ->deleteJson('/nova-api/users/force', [
                'resources' => [$user->id, $user2->id],
            ]);

        $response->assertStatus(200);

        $this->assertCount(0, User::all());

        $this->assertCount(0, DB::connection('sqlite')->table('action_events')->get());
        $this->assertCount(2, DB::connection('sqlite-custom')->table('action_events')->get());

        tap(Nova::actionEvent()->first(), function ($actionEvent) use ($user) {
            $this->assertEquals('Delete', $actionEvent->name);
            $this->assertEquals($user->id, $actionEvent->target_id);
        });
    }
}
