<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Nova;
use Laravel\Nova\Tests\IntegrationTest;

class AssetServeTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanServeScripts()
    {
        Nova::script('nova-tool', __DIR__.'/../Fixtures/assets/tool.js');

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/scripts/nova-tool');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript');
        $this->assertTrue($response->isValidateable());
        $this->assertTrue($response->mustRevalidate());

        $this->withExceptionHandling()
             ->get('/nova-api/scripts/nova-tool', ['If-Modified-Since' => $response->headers->get('Last-Modified')])
             ->assertStatus(304);
    }

    public function testCanServeStyles()
    {
        Nova::style('nova-tool', __DIR__.'/../Fixtures/assets/tool.css');

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/styles/nova-tool');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $this->assertTrue($response->isValidateable());
        $this->assertTrue($response->mustRevalidate());

        $this->withExceptionHandling()
             ->get('/nova-api/styles/nova-tool', ['If-Modified-Since' => $response->headers->get('Last-Modified')])
             ->assertStatus(304);
    }

    public function test404IsReturnedIfScriptDoesntExist()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/scripts/invalid-script.js.map');

        $response->assertStatus(404);
    }

    public function test404IsReturnedIfStyleDoesntExist()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/styles/invalid-style.css');

        $response->assertStatus(404);
    }
}
