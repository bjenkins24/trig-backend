<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Tests\IntegrationTest;

class StackTest extends IntegrationTest
{
    public function testStackFieldsResolveTheCorrectValues()
    {
        $field = Stack::make('Details', [
            $line = Line::make('Name'),
            $text = Text::make('Subtitle'),
        ]);

        $field->resolveForDisplay((object) [
            'name' => 'David Hemphill',
        ]);

        $this->assertSubset([
            'lines' => [
                $line,
                $text,
            ],
        ], $field->jsonSerialize());
    }

    public function testStackItemsResolveCorrectly()
    {
        $line = Line::make('Name');

        $this->assertSubset([
            'classes' => [Line::$classes['large']],
        ], $line->jsonSerialize());

        // ----------------------------------------- //

        $line = Line::make('Name')->asSubTitle();

        $this->assertSubset([
            'classes' => [Line::$classes['medium']],
        ], $line->jsonSerialize());

        // ----------------------------------------- //

        $line = Line::make('Name')->asBase();

        $this->assertSubset([
            'classes' => [Line::$classes['large']],
        ], $line->jsonSerialize());

        // ----------------------------------------- //

        $line = Line::make('Name')->asSmall();

        $this->assertSubset([
            'classes' => [Line::$classes['small']],
        ], $line->jsonSerialize());

        // ----------------------------------------- //

        $line = Line::make('Name')->extraClasses('italic');

        $this->assertSubset([
            'classes' => [Line::$classes['large'], 'italic'],
        ], $line->jsonSerialize());
    }
}
