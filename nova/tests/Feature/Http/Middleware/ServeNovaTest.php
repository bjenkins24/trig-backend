<?php

namespace Laravel\Nova\Tests\Feature\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Laravel\Nova\Events\NovaServiceProviderRegistered;
use Laravel\Nova\Http\Middleware\ServeNova;
use Laravel\Nova\Tests\IntegrationTest;

class ServeNovaTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    /**
     * Uses default path environment setup.
     */
    protected function usesDefaultPath($app)
    {
        $app['config']->set([
            'app.url'     => 'http://localhost',
            'nova.domain' => 'http://localhost',
            'nova.path'   => '/nova',
        ]);
    }

    /**
     * Uses custom subdomain with root path environment setup.
     */
    protected function usesNullDomainWithRootPath($app)
    {
        $app['config']->set([
            'app.url'     => 'http://localhost',
            'nova.domain' => null,
            'nova.path'   => '/',
        ]);
    }

    /**
     * Uses custom subdomain with root path environment setup.
     */
    protected function usesCustomSchemalessSubdomainWithRootPath($app)
    {
        $app['config']->set([
            'app.url'     => 'http://localhost',
            'nova.domain' => 'nova.app',
            'nova.path'   => '/',
        ]);
    }

    /**
     * Uses custom subdomain with root path environment setup.
     */
    protected function usesCustomSubdomainWithRootPath($app)
    {
        $app['config']->set([
            'app.url'     => 'http://localhost',
            'nova.domain' => 'http://nova.app',
            'nova.path'   => '/',
        ]);
    }

    /**
     * Uses custom subdomain with http prefix + root path environment setup.
     */
    protected function usesCustomSubdomainWithPortAndRootPath($app)
    {
        $app['config']->set([
            'app.url'     => 'http://localhost',
            'nova.domain' => 'nova.app:8080',
            'nova.path'   => '/',
        ]);
    }

    /**
     * Uses custom subdomain with http prefix + root path environment setup.
     */
    protected function usesCustomSubdomainWithHttpPrefixAndRootPath($app)
    {
        $app['config']->set([
            'app.url'     => 'http://localhost',
            'nova.domain' => 'httpsnova.app',
            'nova.path'   => '/',
        ]);
    }

    /**
     * @environment-setup usesDefaultPath
     */
    public function testItCanServeFromDefaultPathWillTriggerServiceProviderRegistered()
    {
        Event::fake();

        $this->get('/nova-api/users')->assertOk();

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesNullDomainWithRootPath
     */
    public function testItCanServeFromNullSubdomainWillTriggerServiceProviderRegistered()
    {
        Event::fake();

        $request = Request::create('http://nova.app/nova-api/users');

        $serveNova = new ServeNova();

        $response = $serveNova->handle($request, function (Request $request) {
            return 'OK';
        });

        $this->assertSame('OK', $response);

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSubdomainWithRootPath
     */
    public function testItCanServeFromSubdomainWillTriggerServiceProviderRegistered()
    {
        Event::fake();

        $request = Request::create('http://nova.app/nova-api/users');

        $serveNova = new ServeNova();

        $response = $serveNova->handle($request, function (Request $request) {
            return 'OK';
        });

        $this->assertSame('OK', $response);

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSchemalessSubdomainWithRootPath
     */
    public function testItCanServeFromSchemalessSubdomainWillTriggerServiceProviderRegistered()
    {
        Event::fake();

        $request = Request::create('http://nova.app/nova-api/users');

        $serveNova = new ServeNova();

        $response = $serveNova->handle($request, function (Request $request) {
            return 'OK';
        });

        $this->assertSame('OK', $response);

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSubdomainWithPortAndRootPath
     */
    public function testItCanServeFromSubdomainWithPortWillTriggerServiceProviderRegistered()
    {
        Event::fake();

        $request = Request::create('http://nova.app:8080/nova-api/users');

        $serveNova = new ServeNova();

        $response = $serveNova->handle($request, function (Request $request) {
            return 'OK';
        });

        $this->assertSame('OK', $response);

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSubdomainWithHttpPrefixAndRootPath
     */
    public function testItCanServeFromSubdomainWithHttpPrefixWillTriggerServiceProviderRegistered()
    {
        Event::fake();

        $request = Request::create('http://httpsnova.app/nova-api/users');

        $serveNova = new ServeNova();

        $response = $serveNova->handle($request, function (Request $request) {
            return 'OK';
        });

        $this->assertSame('OK', $response);

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesDefaultPath
     */
    public function testItCanServeFromDefaultPathWillNotTriggerServiceProviderRegisteredOnNoneNovaRequest()
    {
        Event::fake();

        $this->app['router']->get('test', function (Request $request) {
            return 'OK';
        });

        $this->get('http://localhost/test')->assertOk();

        Event::assertNotDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesNullDomainWithRootPath
     */
    public function testItCanServeFromNullSubdomainWillTriggerServiceProviderRegisteredOnNoneNovaRequest()
    {
        Event::fake();

        $this->app['router']->get('test', function (Request $request) {
            return 'OK';
        });

        $this->get('http://localhost/test')->assertOk();

        Event::assertDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSubdomainWithRootPath
     */
    public function testItCanServeFromSubdomainWillTriggerServiceProviderRegisteredOnNoneNovaRequest()
    {
        Event::fake();

        $this->app['router']->get('test', function (Request $request) {
            return 'OK';
        });

        $this->get('http://localhost/test')->assertOk();

        Event::assertNotDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSchemalessSubdomainWithRootPath
     */
    public function testItCanServeFromSchemalessSubdomainWillNotTriggerServiceProviderRegisteredOnNoneNovaRequest()
    {
        Event::fake();

        $this->app['router']->get('test', function (Request $request) {
            return 'OK';
        });

        $this->get('http://localhost/test')->assertOk();

        Event::assertNotDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSubdomainWithPortAndRootPath
     */
    public function testItCanServeFromSubdomainWithPortWillTriggerServiceProviderRegisteredOnNoneNovaRequest()
    {
        Event::fake();

        $this->app['router']->get('test', function (Request $request) {
            return 'OK';
        });

        $this->get('http://localhost/test')->assertOk();

        Event::assertNotDispatched(NovaServiceProviderRegistered::class);
    }

    /**
     * @environment-setup usesCustomSubdomainWithHttpPrefixAndRootPath
     */
    public function testItCanServeFromSubdomainWithHttpPrefixWillTriggerServiceProviderRegisteredOnNoneNovaRequest()
    {
        Event::fake();

        $this->app['router']->get('test', function (Request $request) {
            return 'OK';
        });

        $this->get('http://localhost/test')->assertOk();

        Event::assertNotDispatched(NovaServiceProviderRegistered::class);
    }
}
