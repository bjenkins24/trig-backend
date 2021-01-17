<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Tests\IntegrationTest;

class BadgeTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testBadgeResolvesCorrectValueAndDisplayClass()
    {
        $field = Badge::make('Status');

        $field->resolve((object) ['status' => 'danger']);

        $result = $field->jsonSerialize();

        $this->assertEquals('danger', $result['value']);
        $this->assertEquals('bg-danger-light text-danger-dark', $result['typeClass']);
        $this->assertEquals('danger', $result['label']);
    }

    public function testComputedBadgeResolvesCorrectValueAndDisplayClass()
    {
        $field = Badge::make('Status', function () {
            return 'info';
        });

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('info', $field->value);
        $this->assertEquals('bg-info-light text-info-dark', $result['typeClass']);
        $this->assertEquals('info', $result['label']);
    }

    public function testComputedBadgeWithCustomMapResolvesCorrectValueAndDisplayClass()
    {
        $field = Badge::make('Status', function () {
            return 'draft';
        })->map([
            'draft'     => 'danger',
            'published' => 'success',
        ]);

        $field->resolveForDisplay((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('draft', $field->value);
        $this->assertEquals('bg-danger-light text-danger-dark', $result['typeClass']);
        $this->assertEquals('draft', $result['label']);
    }

    public function testBadgeWithCustomClassMapReturnsCorrectClass()
    {
        $field = Badge::make('Status')->map([
            'draft'     => 'info',
            'published' => 'success',
        ]);

        $field->resolve((object) ['status' => 'published']);

        $result = $field->jsonSerialize();

        $this->assertEquals('published', $field->value);
        $this->assertEquals('bg-success-light text-success-dark', $result['typeClass']);
        $this->assertEquals('published', $result['label']);
    }

    public function testComputedBadgeWithCustomClassMapReturnsCorrectClass()
    {
        $field = Badge::make('Status', function () {
            return 'draft';
        })->map([
            'draft'     => 'warning',
            'published' => 'success',
        ]);

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('draft', $field->value);
        $this->assertEquals('bg-warning-light text-warning-dark', $result['typeClass']);
        $this->assertEquals('draft', $result['label']);
    }

    public function testBadgeCanBeCustomizedWithCustomCssClasses()
    {
        $field = Badge::make('Status', function () {
            return 'draft';
        })->types([
            'draft' => 'custom class names',
        ]);

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('draft', $field->value);
        $this->assertEquals('custom class names', $result['typeClass']);
    }

    public function testBadgeCanBeCustomizedWithCustomCssClassesAsArray()
    {
        $field = Badge::make('Status', function () {
            return 'draft';
        })->types([
            'draft' => ['custom', 'class', 'names'],
        ]);

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('draft', $field->value);
        $this->assertEquals(['custom', 'class', 'names'], $result['typeClass']);
    }

    public function testBadgeHasBuiltInDisplayClassesWhenAddingACustomType()
    {
        $field = Badge::make('Status', function () {
            return 'success';
        })->addTypes([
            'draft' => 'custom class names',
        ]);

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('success', $field->value);
        $this->assertEquals('bg-success-light text-success-dark', $result['typeClass']);
    }

    public function testBadgeBuiltInTypeCanBeOverridden()
    {
        $field = Badge::make('Status', function () {
            return 'success';
        })->addTypes([
            'success' => 'custom class names',
        ]);

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('success', $field->value);
        $this->assertEquals('custom class names', $result['typeClass']);
    }

    public function testBadgeCanSetCustomLabels()
    {
        $field = Badge::make('Status')->labels([
            'info'   => 'Active',
            'danger' => 'Refunded',
        ]);

        $field->resolve((object) ['status' => 'danger']);

        $result = $field->jsonSerialize();

        $this->assertEquals('Refunded', $result['label']);
    }

    public function testBadgeWithCustomMappingAndLabels()
    {
        $field = Badge::make('Status')->map([
            true  => 'success',
            false => 'danger',
        ])->labels([
            true  => 'Yes',
            false => 'No',
        ]);

        $field->resolve((object) ['status' => true]);

        $result = $field->jsonSerialize();

        $this->assertEquals('Yes', $result['label']);

        $field->resolve((object) ['status' => false]);

        $result = $field->jsonSerialize();

        $this->assertEquals('No', $result['label']);
    }

    public function testBadgeCanUseACustomLabel()
    {
        $field = Badge::make('Status', function () {
            return 'danger';
        })->label(function ($value) {
            return 'Custom: '.$value;
        });

        $field->resolve((object) []);

        $result = $field->jsonSerialize();

        $this->assertEquals('danger', $field->value);
        $this->assertEquals('bg-danger-light text-danger-dark', $result['typeClass']);
        $this->assertEquals('Custom: danger', $result['label']);
    }
}
