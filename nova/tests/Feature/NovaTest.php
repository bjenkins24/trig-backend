<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Actions\ActionResource;
use Laravel\Nova\Exceptions\NovaExceptionHandler;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\DiscussionResource;
use Laravel\Nova\Tests\Fixtures\ForbiddenUserResource;
use Laravel\Nova\Tests\Fixtures\NotAvailableForNavigationUserResource;
use Laravel\Nova\Tests\Fixtures\NotSearchableUserResource;
use Laravel\Nova\Tests\Fixtures\TagResource;
use Laravel\Nova\Tests\Fixtures\UserResource;
use Laravel\Nova\Tests\IntegrationTest;

class NovaTest extends IntegrationTest
{
    public function testNovaVersion()
    {
        $this->assertFalse(Cache::driver('array')->has('nova.version'));

        $version = Nova::version();

        $this->assertTrue((bool) preg_match_all('/^3\.\d+\.\d+/s', Nova::version()));
        $this->assertSame(Cache::driver('array')->get('nova.version'), Nova::version());
        $this->assertSame($version, Nova::version());
    }

    public function testNovaCanUseACustomReportCallback()
    {
        $_SERVER['nova.exception.error_handled'] = false;

        $this->assertFalse($_SERVER['nova.exception.error_handled']);

        Nova::report(function ($exception) {
            $_SERVER['nova.exception.error_handled'] = true;
        });

        app(NovaExceptionHandler::class)->report(new \Exception('It did not work'));

        $this->assertTrue($_SERVER['nova.exception.error_handled']);

        unset($_SERVER['nova.exception.error_handled']);
    }

    public function testReturnsTheConfiguredActionResource()
    {
        $this->assertEquals(ActionResource::class, Nova::actionResource());

        config(['nova.actions.resource' => CustomActionResource::class]);

        $this->assertEquals(CustomActionResource::class, Nova::actionResource());
    }

    public function testReturnsTheConfiguredActionResourceModelInstance()
    {
        $this->assertInstanceOf(ActionEvent::class, Nova::actionEvent());

        config(['nova.actions.resource' => CustomActionResource::class]);

        $this->assertInstanceOf(CustomActionEvent::class, Nova::actionEvent());
    }

    public function testHasDefaultSidebarSortingStrategy()
    {
        $callback = function ($resource) {
            return $resource::label();
        };

        $this->assertEquals($callback, Nova::sortResourcesWith());
    }

    public function testCanSpecifyUserSortableClosureForSorting()
    {
        $callback = function ($resource) {
            return $resource::$priority;
        };

        Nova::sortResourcesBy($callback);

        $this->assertEquals($callback, Nova::$sortCallback);

        Nova::sortResourcesBy(function ($resource) {
            return $resource::label();
        });
    }

    public function testCanGetAvailableResources()
    {
        Nova::replaceResources([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
        ]);

        $this->assertEquals([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
        ], Nova::availableResources(NovaRequest::create('/')));
    }

    public function testOnlyAuthorizedResourcesAreReturned()
    {
        Nova::replaceResources([
            DiscussionResource::class,
            ForbiddenUserResource::class,
        ]);

        $this->assertEquals([
            DiscussionResource::class,
        ], Nova::availableResources(NovaRequest::create('/')));
    }

    public function testOnlyAvailableForNavigationResourcesAreReturned()
    {
        Nova::replaceResources([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
            NotAvailableForNavigationUserResource::class,
        ]);

        $this->assertEquals([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
        ], Nova::resourcesForNavigation(NovaRequest::create('/')));
    }

    public function testOnlyGloballySearchableResourcesAreReturned()
    {
        Nova::replaceResources([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
            NotSearchableUserResource::class,
        ]);

        $this->assertEquals([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
        ], Nova::globallySearchableResources(NovaRequest::create('/')));
    }

    public function testResourcesCanBeGroupedForNavigation()
    {
        Nova::replaceResources([
            UserResource::class,
            DiscussionResource::class,
            TagResource::class,
            NotSearchableUserResource::class,
        ]);

        tap(Nova::groupedResourcesForNavigation(NovaRequest::create('/')), function ($resources) {
            $this->assertArrayHasKey('Other', $resources);
            $this->assertArrayHasKey('Content', $resources);

            $this->assertEquals([
                NotSearchableUserResource::class,
                UserResource::class,
            ], $resources['Other']->all());

            $this->assertEquals([
                DiscussionResource::class,
                TagResource::class,
            ], $resources['Content']->all());
        });
    }
}

class CustomActionEvent extends ActionEvent
{
}

class CustomActionResource extends ActionResource
{
    public static $model = CustomActionEvent::class;
}
