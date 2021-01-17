<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Testing\Assert;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Tests\IntegrationTest;

class BooleanGroupTest extends IntegrationTest
{
    public function testByDefaultTheFieldIsDisplayedWithTheNameAsTheLabel()
    {
        $field = BooleanGroup::make('Sizes')->options([
            'create',
            'delete',
        ]);

        $this->assertEquals([
            ['name' => 'create', 'label' => 'create'],
            ['name' => 'delete', 'label' => 'delete'],
        ], $field->jsonSerialize()['options']);
    }

    public function testTheFieldIsDisplayedWithFriendlyLabels()
    {
        $field = BooleanGroup::make('Sizes')->options([
            'create' => 'Create',
            'delete' => 'Delete',
        ]);

        $this->assertEquals([
            ['name' => 'create', 'label' => 'Create'],
            ['name' => 'delete', 'label' => 'Delete'],
        ], $field->jsonSerialize()['options']);
    }

    public function testTheFieldCanAcceptClosuresAsOptions()
    {
        $field = BooleanGroup::make('Sizes')->options(function () {
            return [
                'create' => 'Create',
                'delete' => 'Delete',
            ];
        });

        $this->assertEquals([
            ['name' => 'create', 'label' => 'Create'],
            ['name' => 'delete', 'label' => 'Delete'],
        ], $field->jsonSerialize()['options']);
    }

    public function testTheFieldCanAcceptCollectionsAsOptions()
    {
        $field = BooleanGroup::make('Sizes')->options(collect([
            (object) ['id' => 1, 'name' => 'create', 'label' => 'Create'],
            (object) ['id' => 2, 'name' => 'delete', 'label' => 'Delete'],
        ])->pluck('label', 'name'));

        $this->assertEquals([
            ['name' => 'create', 'label' => 'Create'],
            ['name' => 'delete', 'label' => 'Delete'],
        ], $field->jsonSerialize()['options']);
    }

    public function testTheFieldCanHideTrueValues()
    {
        $field = BooleanGroup::make('Sizes')->options([
            'create',
            'delete',
        ])->hideTrueValues();

        Assert::assertArraySubset([
            'hideTrueValues' => true,
        ], $field->jsonSerialize());
    }

    public function testTheFieldCanHideFalseValuesFromIndex()
    {
        $field = BooleanGroup::make('Sizes')->options([
            'create',
            'delete',
        ])->hideFalseValues();

        $this->assertTrue($field->jsonSerialize()['hideFalseValues']);

        Assert::assertArraySubset([
            'hideFalseValues' => true,
        ], $field->jsonSerialize());
    }

    public function testTheFieldCanChangeNoDataText()
    {
        $field = BooleanGroup::make('Sizes')->options([
            'create' => 'Create',
            'delete' => 'Delete',
        ])->noValueText('Custom No Data');

        Assert::assertArraySubset([
            'noValueText' => 'Custom No Data',
        ], $field->jsonSerialize());
    }
}
