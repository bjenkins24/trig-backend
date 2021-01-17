<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Assert;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tests\Fixtures\File;
use Laravel\Nova\Tests\Fixtures\FileResource;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\PostResource;
use Laravel\Nova\Tests\Fixtures\UserResource;
use Laravel\Nova\Tests\IntegrationTest;
use stdClass;

class FieldTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        Field::$customComponents = [];
    }

    public function testComponentCanBeCustomized()
    {
        Text::useComponent('something');
        $this->assertEquals('something', (new Text('Foo', 'foo'))->component());

        $this->assertEquals('belongs-to-field', (new BelongsTo('User', 'user', UserResource::class))->component());
    }

    public function testFieldsCanHaveCustomDisplayCallback()
    {
        $field = Text::make('Name')->displayUsing(function ($value) {
            return strtoupper($value);
        });

        $field->resolve((object) ['name' => 'Taylor'], 'name');
        $this->assertEquals('Taylor', $field->value);

        $field->resolveForDisplay((object) ['name' => 'Taylor'], 'name');
        $this->assertEquals('TAYLOR', $field->value);
    }

    public function testFieldsCanHaveCustomResolverCallback()
    {
        $field = Text::make('Name')->resolveUsing(function ($value, $model, $attribute) {
            return strtoupper($value);
        });

        $field->resolve((object) ['name' => 'Taylor'], 'name');

        $this->assertEquals('TAYLOR', $field->value);
    }

    public function testFieldsCanHaveCustomResolverCallbackEvenIfFieldIsMissing()
    {
        $field = Text::make('Name')->resolveUsing(function ($value, $model, $attribute) {
            return strtoupper('default');
        });

        $field->resolve((object) ['name' => 'Taylor'], 'email');

        $this->assertEquals('DEFAULT', $field->value);
    }

    public function testComputedFieldsResolve()
    {
        $field = Text::make('InvokableComputed', function () {
            return 'Computed';
        });

        $field->resolve((object) []);
        $this->assertEquals('Computed', $field->value);
    }

    public function testComputedFieldsResolveForDisplay()
    {
        $field = Text::make('InvokableComputed', function ($resource) {
            return 'Computed';
        });

        $field->resolveForDisplay((object) []);
        $this->assertEquals('Computed', $field->value);
    }

    public function testComputedFieldsResolveForDisplayOnce()
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $field = Text::make('InvokableComputed', function ($resource) {
            return Post::count(); // Do a simple SQL query
        });

        $field->resolveForDisplay((object) []);
        $this->assertEquals(1, count(DB::getQueryLog()));

        DB::flushQueryLog();
        DB::disableQueryLog();
    }

    public function testComputedFieldsUseDisplayCallback()
    {
        $field = Text::make('InvokableComputed', function ($resource) {
            return 'Computed';
        })->displayUsing(function ($value) {
            return sprintf('Displayed Via %s Field', $value);
        });

        $field->resolveForDisplay((object) []);
        $this->assertEquals('Displayed Via Computed Field', $field->value);
    }

    public function testComputedFieldsResolveOnceWithDisplayCallback()
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $field = Text::make('InvokableComputed', function ($resource) {
            return Post::count();
        })->displayUsing(function ($value) {
            return sprintf('Count Is %s', $value);
        });

        $field->resolveForDisplay((object) []);
        $this->assertEquals(1, count(DB::getQueryLog()));

        DB::flushQueryLog();
        DB::disableQueryLog();
    }

    public function testComputedFieldsResolveWithResource()
    {
        $field = Text::make('InvokableComputed', function ($resource) {
            return $resource->value;
        });

        $field->resolve((object) ['value' => 'Computed']);
        $this->assertEquals('Computed', $field->value);
    }

    public function testComputedFieldsResolveForDisplayWithResource()
    {
        $field = Text::make('InvokableComputed', function ($resource) {
            return $resource->value;
        });

        $field->resolveForDisplay((object) ['value' => 'Other value']);
        $this->assertEquals('Other value', $field->value);
    }

    public function testCanSeeWhenProxiesToGate()
    {
        unset($_SERVER['__nova.ability']);

        $field = Text::make('Name')->canSeeWhen('view-profile');
        $callback = $field->seeCallback;

        $request = Request::create('/', 'GET');

        $request->setUserResolver(function () {
            return new class() {
                public function can($ability, $arguments = [])
                {
                    $_SERVER['__nova.ability'] = $ability;

                    return true;
                }
            };
        });

        $this->assertTrue($callback($request));
        $this->assertEquals('view-profile', $_SERVER['__nova.ability']);
    }

    public function testTextareaFieldsDontShowTheirContentByDefault()
    {
        $textarea = Textarea::make('Name');
        $trix = Trix::make('Name');
        $markdown = Trix::make('Name');

        $this->assertFalse($textarea->shouldBeExpanded());
        $this->assertFalse($trix->shouldBeExpanded());
        $this->assertFalse($markdown->shouldBeExpanded());
    }

    public function testTextareaFieldsCanBeSetToAlwaysShowTheirContent()
    {
        $textarea = Textarea::make('Name')->alwaysShow();
        $trix = Trix::make('Name')->alwaysShow();
        $markdown = Trix::make('Name')->alwaysShow();

        $this->assertTrue($textarea->shouldBeExpanded());
        $this->assertTrue($trix->shouldBeExpanded());
        $this->assertTrue($markdown->shouldBeExpanded());
    }

    public function testTextareaFieldsCanHaveCustomShouldShowCallback()
    {
        $callback = function () {
            return true;
        };

        $textarea = Textarea::make('Name')->shouldShow($callback);
        $trix = Trix::make('Name')->shouldShow($callback);
        $markdown = Trix::make('Name')->shouldShow($callback);

        $this->assertTrue($textarea->shouldBeExpanded());
        $this->assertTrue($trix->shouldBeExpanded());
        $this->assertTrue($markdown->shouldBeExpanded());
    }

    public function testTextFieldsCanBeSerialized()
    {
        $field = Text::make('Name');

        Assert::assertArraySubset([
            'component'       => 'text-field',
            'prefixComponent' => true,
            'indexName'       => 'Name',
            'name'            => 'Name',
            'attribute'       => 'name',
            'value'           => null,
            'panel'           => null,
            'sortable'        => false,
            'textAlign'       => 'left',
        ], $field->jsonSerialize());
    }

    public function testTextFieldsCanHaveAnArrayOfSuggestions()
    {
        $field = Text::make('Name')->suggestions([
            'Taylor',
            'David',
            'Mohammed',
            'Dries',
            'James',
        ]);

        $this->app->instance(
            NovaRequest::class,
            NovaRequest::create('/', 'GET', [
                'editing'  => true,
                'editMode' => 'create',
            ])
        );

        Assert::assertArraySubset([
            'suggestions' => [4 => 'James'],
        ], $field->jsonSerialize());
    }

    public function testTextFieldsCanHaveSuggestionsFromAClosure()
    {
        $field = Text::make('Name')->suggestions(function () {
            return [
                'Taylor',
                'David',
                'Mohammed',
                'Dries',
                'James',
            ];
        });

        $this->app->instance(
            NovaRequest::class,
            NovaRequest::create('/', 'GET', [
                'editing'  => true,
                'editMode' => 'create',
            ])
        );

        Assert::assertArraySubset([
            'suggestions' => [4 => 'James'],
        ], $field->jsonSerialize());
    }

    public function testTextFieldsCanUseCallableArrayAsSuggestions()
    {
        $field = Text::make('Sizes')->suggestions(['Laravel\Nova\Tests\Feature\SuggestionOptions', 'options']);

        $this->app->instance(
            NovaRequest::class,
            NovaRequest::create('/', 'GET', [
                'editing'  => true,
                'editMode' => 'create',
            ])
        );

        Assert::assertArraySubset([
            'suggestions' => [
                'Taylor',
                'David',
                'Mohammed',
                'Dries',
                'James',
            ],
        ], $field->jsonSerialize());
    }

    public function testTextFieldsCanHaveExtraMetaData()
    {
        $field = Text::make('Name')->withMeta(['extraAttributes' => [
            'placeholder' => 'This is a placeholder',
        ]]);

        Assert::assertArraySubset([
            'extraAttributes' => ['placeholder' => 'This is a placeholder'],
        ], $field->jsonSerialize());
    }

    public function testSelectFieldsOptionsWithAdditionalParameters()
    {
        $expected = [
            ['label' => 'A', 'value' => 'a'],
            ['label' => 'B', 'value' => 'b'],
            ['label' => 'C', 'value' => 'c'],
            ['label' => 'D', 'value' => 'd', 'group' => 'E'],
        ];
        $field = Select::make('Name')->options([
            'a'      => 'A',
            'b'      => ['label' => 'B'],
            ['value' => 'c', 'label' => 'C'],
            ['value' => 'd', 'label' => 'D', 'group' => 'E'],
        ]);

        $this->assertJsonStringEqualsJsonString(json_encode($expected), json_encode($field->jsonSerialize()['options']));
    }

    public function testFieldCanBeSetToReadonly()
    {
        $field = Text::make('Avatar');
        $field->readonly(true);

        $this->assertTrue($field->isReadonly(NovaRequest::create('/', 'get')));
    }

    public function testFieldCanBeSetToReadonlyUsingACallback()
    {
        $field = Text::make('Avatar');
        $field->readonly(function () {
            return true;
        });

        $this->assertTrue($field->isReadonly(NovaRequest::create('/', 'get')));
    }

    public function testFieldCanBeSetToNotBeReadonlyUsingACallback()
    {
        $field = Text::make('Avatar');
        $field->readonly(function () {
            return false;
        });

        $this->assertFalse($field->isReadonly(NovaRequest::create('/', 'get')));
    }

    public function testCanSetFieldToReadonlyOnCreateRequests()
    {
        $request = NovaRequest::create('/nova-api/users', 'POST', [
            'editing'  => true,
            'editMode' => 'create',
        ]);

        $field = Text::make('Name')->readonly(function ($request) {
            return $request->isCreateOrAttachRequest();
        });

        $this->assertTrue($field->isReadonly($request));
    }

    public function testCanSetFieldToReadonlyOnUpdateRequests()
    {
        $request = NovaRequest::create('/nova-api/users/1', 'PUT', [
            'editing'  => true,
            'editMode' => 'update',
        ]);

        $field = Text::make('Name')->readonly(function ($request) {
            return $request->isUpdateOrUpdateAttachedRequest();
        });

        $this->assertTrue($field->isReadonly($request));
    }

    public function testCollisionOfRequestProperties()
    {
        $request = new NovaRequest([], [
            'query'    => '',
            'resource' => 'resource',
        ]);

        $request->setMethod('POST');
        $request->setRouteResolver(function () use ($request) {
            return tap(new Route('POST', '/{resource}', function () {
            }), function (Route $route) use ($request) {
                $route->bind($request);
                $route->setParameter('resource', UserResource::class);
            });
        });

        $model = new stdClass();

        Text::make('Resource')->fill($request, $model);
        Password::make('Query')->fill($request, $model);

        $this->assertObjectNotHasAttribute('query', $model);
        $this->assertEquals('resource', $model->resource);
    }

    public function testFieldsAreNotRequiredByDefault()
    {
        $request = NovaRequest::create('/nova-api/users/creation-fields', 'GET');

        $field = Text::make('Name');

        $this->assertFalse($field->isRequired($request));
    }

    public function testCanMarkAFieldAsRequiredForCreateIfInValidation()
    {
        $request = NovaRequest::create('/nova-api/users/creation-fields', 'GET', [
            'editing'  => true,
            'editMode' => 'create',
        ]);

        $field = Text::make('Name')->rules('required');

        $this->assertTrue($field->isRequired($request));
    }

    public function testCanMarkAFieldAsRequiredForUpdateIfInValidation()
    {
        $request = NovaRequest::create('/nova-api/users/update-fields', 'GET', [
            'editing'  => true,
            'editMode' => 'update',
        ]);

        $field = Text::make('Name')->rules('required');

        $this->assertTrue($field->isRequired($request));
    }

    public function testCanMarkAFieldAsRequiredUsingCallback()
    {
        $request = NovaRequest::create('/nova-api/users', 'GET');

        $field = Text::make('Name')->required();

        $this->assertTrue($field->isRequired($request));

        $field = Text::make('Name')->required(function () {
            return false;
        });

        $this->assertFalse($field->isRequired($request));
    }

    public function testResolveOnlyCoverField()
    {
        $request = NovaRequest::create('/nova-api/files', 'GET');

        $_SERVER['nova.fileResource.additionalField'] = function () {
            return Text::make('Text', function () {
                throw new \Exception('This field should not be resolved.');
            });
        };

        $_SERVER['nova.fileResource.imageField'] = function () {
            return Avatar::make('Avatar', 'avatar', null);
        };

        $url = (new FileResource(new File(['avatar' => 'avatars/avatar.jpg'])))->resolveAvatarUrl($request);

        $this->assertEquals('/storage/avatars/avatar.jpg', $url);

        unset($_SERVER['nova.fileResource.additionalField'], $_SERVER['nova.fileResource.imageField']);
    }

    public function testCanMarkAFieldAsStackedUsingBoolean()
    {
        $field = Text::make('Avatar');
        $field->stacked(true);

        $this->assertTrue($field->stacked);

        $field->stacked(false);

        $this->assertFalse($field->stacked);
    }

    public function testBelongsToFieldCanHaveCustomCallbackToDetermineIfWeShouldShowCreateRelationButton()
    {
        $request = NovaRequest::create('/', 'GET', []);

        $field = BelongsTo::make('User', 'user', UserResource::class);

        $field->showCreateRelationButton(false);
        $this->assertFalse($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton(true);
        $this->assertTrue($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton(function ($request) {
            return false;
        });
        $this->assertFalse($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton(function ($request) {
            return true;
        });
        $this->assertTrue($field->createRelationShouldBeShown($request));

        $field->hideCreateRelationButton();
        $this->assertFalse($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton();
        $this->assertTrue($field->createRelationShouldBeShown($request));
    }

    public function testMorphToFieldsCanHaveCustomCallbackToDetermineIfWeShouldShowCreateRelationButton()
    {
        $request = NovaRequest::create('/', 'GET', []);

        $field = MorphTo::make('Commentable', 'commentable');

        $field->showCreateRelationButton(false);
        $this->assertFalse($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton(true);
        $this->assertTrue($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton(function ($request) {
            return false;
        });
        $this->assertFalse($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton(function ($request) {
            return true;
        });
        $this->assertTrue($field->createRelationShouldBeShown($request));

        $field->hideCreateRelationButton();
        $this->assertFalse($field->createRelationShouldBeShown($request));

        $field->showCreateRelationButton();
        $this->assertTrue($field->createRelationShouldBeShown($request));
    }

    public function testFieldsCanHaveHelpText()
    {
        $field = Text::make('Name')->help('Custom help text.');

        $this->assertSubset([
            'helpText' => 'Custom help text.',
        ], $field->jsonSerialize());
    }

    public function testFieldsCanSpecifyADefaultValueAsCallback()
    {
        $field = Text::make('Name')->default(function (NovaRequest $request) {
            return $request->url();
        });

        $this->app->instance(
            NovaRequest::class,
            NovaRequest::create('/', 'GET', [
                'editing'  => true,
                'editMode' => 'create',
            ])
        );

        $this->assertSubset([
            'value' => 'http://localhost',
        ], $field->jsonSerialize());
    }

    public function testFieldsCanSpecifyADefaultValue()
    {
        $field = Text::make('Name')->default('David Hemphill');

        $this->app->instance(
            NovaRequest::class,
            NovaRequest::create('/', 'GET', [
                'editing'  => true,
                'editMode' => 'create',
            ])
        );

        $this->assertSubset([
            'value' => 'David Hemphill',
        ], $field->jsonSerialize());
    }

    public function testBelongsToFieldsSupportDefaultValues()
    {
        $_SERVER['nova.user.default-value'] = 4;

        $this->authenticate()
            ->withoutExceptionHandling()
            ->getJson('/nova-api/posts/creation-fields?editing=true&editMode=create')
            ->assertJson([
                'fields' => [
                    [
                        'name'      => 'User',
                        'component' => 'belongs-to-field',
                        'value'     => 4, // This is the default value of the field.
                    ],
                ],
            ]);

        unset($_SERVER['nova.user.default-value']);
    }

    public function testMorphToFieldsSupportDefaultValues()
    {
        $_SERVER['nova.user.default-value'] = 4;
        $_SERVER['nova.user.default-resource'] = PostResource::class;

        $this->authenticate()
            ->withoutExceptionHandling()
            ->getJson('/nova-api/comments/creation-fields?editing=true&editMode=create')
            ->assertJson([
                'fields' => [
                    1 => [
                        'name'            => 'Commentable',
                        'component'       => 'morph-to-field',
                        'value'           => 4, // This is the default value of the field.
                        'defaultResource' => 'posts',
                    ],
                ],
            ]);

        unset($_SERVER['nova.user.default-value']);
        unset($_SERVER['nova.user.default-resource']);
    }

    public function testHeadingFieldsCanBeComputed()
    {
        $field = Heading::make('InvokableComputed', function () {
            return 'Computed';
        });

        $field->resolve((object) ['name' => 'David']);
        $this->assertEquals('Computed', $field->value);
    }

    public function testFieldCanHavePlaceholderText()
    {
        $field = Text::make('Name')->placeholder('This is placeholder text.');

        $this->assertSubset([
            'extraAttributes' => [
                'placeholder' => 'This is placeholder text.',
            ],
        ], $field->jsonSerialize());
    }
}

class SuggestionOptions
{
    public static function options()
    {
        return [
            'Taylor',
            'David',
            'Mohammed',
            'Dries',
            'James',
        ];
    }
}
