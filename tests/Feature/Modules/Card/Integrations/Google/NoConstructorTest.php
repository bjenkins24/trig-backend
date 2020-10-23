<?php

namespace Tests\Feature\Modules\Card\Integrations\Google;

use App\Modules\Card\Integrations\Google\NoConstructor;
use Tests\TestCase;

class NoConstructorTest extends TestCase
{
    public function testNoConstructor()
    {
        $this->partialMock(NoConstructor::class, static function ($mock) {
            $mock->shouldReceive('doNothing')->andReturn('');
        });
        $helloWorld = app(NoConstructor::class)->getFoo();
        self::assertEquals('bar', $helloWorld);
    }
}
