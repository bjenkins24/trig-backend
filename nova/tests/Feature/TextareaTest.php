<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Tests\IntegrationTest;

class TextareaTest extends IntegrationTest
{
    public function testFieldContentIsEscapedForDisplay()
    {
        $field = Textarea::make('Body');
        $xssString = '<img src="null" onError="alert("XSS")" />';

        $field->resolve((object) ['body' => $xssString], 'body');
        $this->assertEquals($xssString, $field->value);

        $field->resolveForDisplay((object) ['body' => $xssString], 'body');
        $this->assertEquals(e($xssString), $field->value);
    }
}
