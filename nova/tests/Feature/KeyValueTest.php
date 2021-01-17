<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class KeyValueTest extends IntegrationTest
{
    public function testFieldCanBeResolved()
    {
        $field = KeyValue::make('Meta');
        $user = factory(User::class)->create();
        $user->update(['meta' => ['age' => 35, 'weight' => 170]]);

        $field->resolve($user);
        $this->assertEquals(['age' => 35, 'weight' => 170], $field->value);
    }

    public function testFieldCanBeResolvedForDisplay()
    {
        $field = KeyValue::make('Meta');
        $user = factory(User::class)->create();
        $user->update(['meta' => ['age' => 35, 'weight' => 170]]);

        $field->resolveForDisplay($user);
        $this->assertEquals(['age' => 35, 'weight' => 170], $field->value);
    }

    public function testFieldCanHaveDefaultValue()
    {
        $field = KeyValue::make('Meta');
        $field->default([
            'age'    => '',
            'weight' => '',
        ]);

        $this->app->instance(
            NovaRequest::class,
            NovaRequest::create('/', 'GET', [
                'editing'  => true,
                'editMode' => 'create',
            ])
        );

        $this->assertSubset([
            'value' => [
                'age'    => '',
                'weight' => '',
            ],
        ], $field->jsonSerialize());
    }

    public function testTheFieldsKeysCanBeLockedForEditing()
    {
        $field = KeyValue::make('Meta');
        $field->disableEditingKeys();

        $request = NovaRequest::create('/', 'GET', [
            'editing'  => true,
            'editMode' => 'create',
        ]);

        $this->assertTrue($field->readonlyKeys($request));
        $this->assertSubset(['readonlyKeys' => true], $field->jsonSerialize());
    }

    public function testAddingRowsToKeyValueFieldsCanBeDisabled()
    {
        $field = KeyValue::make('Meta');
        $this->assertTrue($field->canAddRow);
        $this->assertSubset(['canAddRow' => true], $field->jsonSerialize());
        $field->disableAddingRows();

        $this->assertFalse($field->canAddRow);
        $this->assertSubset(['canAddRow' => false], $field->jsonSerialize());
    }

    public function testDeletingRowsToKeyValueFieldsCanBeDisabled()
    {
        $field = KeyValue::make('Meta');
        $this->assertTrue($field->canDeleteRow);
        $this->assertSubset(['canDeleteRow' => true], $field->jsonSerialize());
        $field->disableDeletingRows();

        $this->assertFalse($field->canDeleteRow);
        $this->assertSubset(['canDeleteRow' => false], $field->jsonSerialize());
    }
}
