<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\PostResource;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class NovaRequestTest extends IntegrationTest
{
    public function testCheckingIfCreateRequest()
    {
        $request = NovaRequest::create('/nova-api/users/1', 'POST', [
            'editing'  => true,
            'editMode' => 'create',
        ]);

        $this->assertTrue($request->isCreateOrAttachRequest());
        $this->assertFalse($request->isUpdateOrUpdateAttachedRequest());
    }

    public function testCheckingIfUpdateRequest()
    {
        $request = NovaRequest::create('/nova-api/users/1', 'PUT', [
            'editing'  => true,
            'editMode' => 'update',
        ]);

        $this->assertTrue($request->isUpdateOrUpdateAttachedRequest());
        $this->assertFalse($request->isCreateOrAttachRequest());
    }

    public function testItBoundNovaRequestToTheContainer()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();

        $request = Request::create('/nova-api/users/action?action=noop-action', 'POST', [
            'resources' => implode(',', [$user->id, $user2->id]),
            'test'      => 'Taylor Otwell',
            'callback'  => '',
        ]);

        $this->app->instance('request', $request);

        $actionRequest = $this->app->make(ActionRequest::class);

        $this->assertTrue($this->app->bound(NovaRequest::class));

        $this->assertSame([
            'resources' => implode(',', [$user->id, $user2->id]),
            'test'      => 'Taylor Otwell',
            'callback'  => '',
            'action'    => 'noop-action',
        ], $actionRequest->all());
    }

    public function testItBoundNovaRequestToResolveCurrentUser()
    {
        $user = factory(User::class)->create();
        $post = factory(Post::class)->create([
            'user_id' => 1,
        ]);
        factory(Post::class, 5)->create([
            'user_id' => 2,
        ]);

        $this->app->make('router')->get('nova-request-test/user', function (NovaRequest $request) {
            $posts = PostResource::indexQuery($request, Post::whereIn('user_id', [$request->user()->id]))->get();

            return [
                'user'  => Arr::only($request->user()->toArray(), ['id', 'name']),
                'posts' => $posts->transform(function ($post) {
                    return ['id' => $post->id, 'title' => $post->title];
                })->toArray(),
            ];
        })->name('nova-request-test-user');

        $this->actingAs($user);

        $response = $this->getJson(route('nova-request-test-user'));

        $response->assertOk()
            ->assertExactJson([
                'user'  => ['id' => $user->id, 'name' => $user->name],
                'posts' => [
                    ['id' => $post->id, 'title' => $post->title],
                ],
            ]);
    }

    public function testItBoundNovaRequestCanGetUserFromResolver()
    {
        $user = factory(User::class)->create();

        $this->app->make('router')->get('nova-request-test/user', function (NovaRequest $request) {
            return [
                'laravel' => Arr::only(app(Request::class)->user()->toArray(), ['id', 'name']),
                'nova'    => Arr::only($request->user()->toArray(), ['id', 'name']),
            ];
        })->name('nova-request-test-user');

        $this->actingAs($user);

        $response = $this->getJson(route('nova-request-test-user'));

        $response->assertOk()
            ->assertExactJson([
                'laravel' => ['id' => $user->id, 'name' => $user->name],
                'nova'    => ['id' => $user->id, 'name' => $user->name],
            ]);
    }
}
