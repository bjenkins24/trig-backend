<?php

namespace Laravel\Nova\Tests\Feature;

use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserResource;
use Laravel\Nova\Tests\IntegrationTest;

class FieldCollectionTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testUnauthorizedFieldsAreNotIncluded()
    {
        $request = NovaRequest::create('/');

        $collection = FieldCollection::make([
            Text::make('Text1')->canSee(function () {
                return false;
            }),
            Text::make('Text2')->canSee(function () {
                return true;
            }),
        ])->authorized($request);

        $this->assertCount(1, $collection);
        $this->assertEquals('text2', $collection->first()->attribute);
    }

    public function testFieldsCanBeResolved()
    {
        $user = factory(User::class)->create();

        $collection = FieldCollection::make([
            Text::make('Name'),
            Text::make('Email'),
        ])->resolve($user);

        $this->assertCount(2, $collection);
        $this->assertEquals($user->name, $collection[0]->value);
        $this->assertEquals($user->email, $collection[1]->value);
    }

    public function testFieldsCanBeResolvedForDisplay()
    {
        $user = factory(User::class)->create();

        $collection = FieldCollection::make([
            Text::make('Name'),
            Text::make('Email')->displayUsing(function ($value) {
                return str_replace('@', '%', $value);
            }),
        ])->resolveForDisplay($user);

        $this->assertCount(2, $collection);
        $this->assertEquals($user->name, $collection[0]->value);
        $this->assertEquals(str_replace('@', '%', $user->email), $collection[1]->value);
    }

    public function testCanFilterByOnlyDetailFields()
    {
        $request = NovaRequest::create('/');

        $collection = FieldCollection::make([
            Text::make('Text1')->hideFromDetail(),
            Text::make('Text2'),
        ])->filterForDetail($request, new \stdClass());

        $this->assertCount(1, $collection);
        $this->assertEquals('text2', $collection->first()->attribute);
    }

    public function testCanFilterByOnlyIndexFields()
    {
        $request = NovaRequest::create('/');

        $collection = FieldCollection::make([
            Text::make('Text1')->hideFromIndex(),
            Text::make('Text2'),
        ])->filterForIndex($request, new \stdClass());

        $this->assertCount(1, $collection);
        $this->assertEquals('text2', $collection->first()->attribute);
    }

    public function testCanRetrieveFieldByAttribute()
    {
        $field = FieldCollection::make([
            Text::make('Text1'),
            Text::make('Text2'),
        ])->findFieldByAttribute('text1');

        $this->assertEquals('text1', $field->attribute);
    }

    public function testCanRejectListableFields()
    {
        $fields = FieldCollection::make([
            BelongsToMany::make('User', 'user', UserResource::class),
            Text::make('Text1'),
        ])->withoutListableFields();

        $this->assertCount(1, $fields);
        $this->assertEquals('text1', $fields->first()->attribute);
    }

    public function testCanFilterToManyToManyRelations()
    {
        $fields = FieldCollection::make([
            BelongsToMany::make('User', 'user', UserResource::class),
            Text::make('Text1'),
        ])->filterForManyToManyRelations();

        $this->assertCount(1, $fields);
        $this->assertEquals('user', $fields->first()->attribute);
    }
}
