<?php

namespace Laravel\Nova\Tests\Feature;

use DateTimeZone;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Tests\IntegrationTest;

class SelectTest extends IntegrationTest
{
    public function testSelectFieldsResolveTheCorrectValues()
    {
        $field = Select::make('Sizes')->options(function () {
            return [
                'L' => 'Large',
                'S' => 'Small',
            ];
        });

        $field->resolve((object) ['size' => 'L'], 'size');
        $this->assertEquals('L', $field->value);

        $field->resolveForDisplay((object) ['size' => 'L'], 'size');
        $this->assertEquals('L', $field->value);
    }

    public function testPassingCallableFunctionNameAsDefaultDoesntCrash()
    {
        $this
            ->withoutExceptionHandling()
            ->authenticate()
            ->getJson('/nova-api/callable-defaults/creation-fields?editing=true&editMode=create')
            ->assertOk();
    }

    public function testSelectFieldsCanDisplayOptionsUsingLabels()
    {
        $field = Select::make('Sizes')->options([
            'L' => 'Large',
            'S' => 'Small',
        ])->displayUsingLabels();

        $this->assertSubset([
            'options' => [
                [
                    'label' => 'Large',
                    'value' => 'L',
                ],
                [
                    'label' => 'Small',
                    'value' => 'S',
                ],
            ],
        ], $field->jsonSerialize());

        $field->resolve((object) ['size' => 'L'], 'size');
        $this->assertEquals('L', $field->value);

        $field->resolveForDisplay((object) ['size' => 'L'], 'size');
        $this->assertEquals('Large', $field->value);
    }

    public function testSelectFieldsCanHaveCustomDisplayCallback()
    {
        $field = Select::make('Sizes')->options([
            'L' => 'Large',
            'S' => 'Small',
        ])->displayUsing(function ($value) {
            return 'Wew';
        });

        $field->resolve((object) ['size' => 'L'], 'size');
        $this->assertEquals('L', $field->value);

        $field->resolveForDisplay((object) ['size' => 'L'], 'size');
        $this->assertEquals('Wew', $field->value);
    }

    public function testSelectFieldsCanUseCallableArrayAsOptions()
    {
        $field = Select::make('Sizes')->options(['DateTimeZone', 'listIdentifiers']);

        $expected = collect(DateTimeZone::listIdentifiers())->map(function ($tz, $key) {
            return ['label' => $tz, 'value' => $key];
        })->all();

        $this->assertSubset(['options' => $expected], $field->jsonSerialize());

        $field->resolve((object) ['timezone' => 'America/Chicago'], 'timezone');
        $this->assertEquals('America/Chicago', $field->value);

        $field->resolveForDisplay((object) ['timezone' => 'America/Chicago'], 'timezone');
        $this->assertEquals('America/Chicago', $field->value);
    }

    public function testSelectFieldsUsingNonCallableArrayWithTwoItems()
    {
        $field = Select::make('Sizes')->options(['Nova', 'site']);

        $this->assertSubset([
            'options' => [
                [
                    'label' => 'Nova',
                    'value' => 0,
                ],
                [
                    'label' => 'site',
                    'value' => 1,
                ],
            ],
        ], $field->jsonSerialize());
    }

    public function testSelectFieldsCanAcceptClosuresAsOptions()
    {
        $field = Select::make('Sizes')->options(function () {
            return [
                'L' => 'Large',
                'S' => 'Small',
            ];
        })->displayUsingLabels();

        $this->assertSubset([
            'options' => [
                [
                    'label' => 'Large',
                    'value' => 'L',
                ],
                [
                    'label' => 'Small',
                    'value' => 'S',
                ],
            ],
        ], $field->jsonSerialize());
    }

    public function testSelectFieldsCanAcceptCollectionsAsOptions()
    {
        $field = Select::make('Sizes')->options(collect([
            'L' => 'Large',
            'S' => 'Small',
        ]));

        $this->assertSubset([
            'options' => [
                [
                    'label' => 'Large',
                    'value' => 'L',
                ],
                [
                    'label' => 'Small',
                    'value' => 'S',
                ],
            ],
        ], $field->jsonSerialize());
    }

    public function testSelectFieldsCanAcceptNonAssociativeCollectionsAsOptions()
    {
        $field = Select::make('Sizes')->options(collect(['L', 'S']));

        $this->assertSubset([
            'options' => [
                [
                    'label' => 'L',
                    'value' => 0,
                ],
                [
                    'label' => 'S',
                    'value' => 1,
                ],
            ],
        ], $field->jsonSerialize());
    }

    public function testSelectFieldIsNotSearchableByDefault()
    {
        $field = Select::make('Sizes')->options(collect(['L', 'S']));

        $this->assertFalse($field->searchable);
        $this->assertSubset([
            'searchable' => false,
        ], $field->jsonSerialize());
    }

    public function testIfFieldIsSearchableAndPlainOptionsSetTheyAreNotFlattened()
    {
        $field = Select::make('Size')->searchable()->options([
            'L' => 'Large',
            'S' => 'Small',
        ]);

        $this->assertSubset([
            'options' => [
                ['label' => 'Large', 'value' => 'L'],
                ['label' => 'Small', 'value' => 'S'],
            ],
        ], $field->jsonSerialize());
    }

    public function testIfFieldIsSearchableGroupOptionsAreFlattenedAndGroupLabelsAreAppendedToTheOptions()
    {
        $field = Select::make('Size')->searchable()->options([
            'MS' => ['label' => 'Small', 'group' => 'Men Sizes'],
        ]);

        $this->assertSubset([
            'options' => [
                ['label' => 'Men Sizes - Small', 'value' => 'MS'],
            ],
        ], $field->jsonSerialize());
    }
}
