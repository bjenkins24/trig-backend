<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\ColumnFilter;
use Laravel\Nova\Tests\Fixtures\Comment;
use Laravel\Nova\Tests\Fixtures\CustomKeyFilter;
use Laravel\Nova\Tests\Fixtures\IdFilter;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;

class ResourceIndexTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanListAResource()
    {
        factory(User::class)->times(50)->create();
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users');

        $this->assertEquals('User Resources', $response->original['label']);
        $this->assertEquals($user->id, $response->original['resources'][0]['id']->value);
        $this->assertTrue($response->original['resources'][0]['authorizedToUpdate']);
        $this->assertTrue($response->original['resources'][0]['authorizedToDelete']);
        $this->assertTrue($response->original['resources'][0]['softDeletes']);
        $this->assertEquals([25, 50, 100], $response->original['per_page_options']);
        $this->assertEquals(51, $response->original['total']);

        $fields = $response->original['resources'][0]['fields'];
        $nameField = collect($fields)->where('attribute', 'name')->first();
        $this->assertEquals($user->name, $nameField->value);

        $response->assertJsonCount(25, 'resources');
    }

    public function testCantListAnInvalidResource()
    {
        $this->withExceptionHandling()
                ->getJson('/nova-api/foo')
                ->assertStatus(404);
    }

    public function testAuthorizationInformationIsCorrectlyAdjustedWhenUnauthorized()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        $user = factory(User::class)->create();

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.updatable'] = false;
        $_SERVER['nova.user.deletable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users');

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.updatable']);
        unset($_SERVER['nova.user.deletable']);

        $this->assertEquals($user->id, $response->original['resources'][0]['id']->value);
        $this->assertFalse($response->original['resources'][0]['authorizedToUpdate']);
        $this->assertFalse($response->original['resources'][0]['authorizedToDelete']);
        $this->assertEquals(3, $response->original['total']);
    }

    public function testCantListAResourceIfNotAuthorizedToViewTheResource()
    {
        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.viewAnyable'] = false;

        Gate::policy(User::class, UserPolicy::class);

        factory(User::class)->create();
        factory(User::class)->create();
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users');

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.viewAnyable']);

        $response->assertStatus(403);
    }

    public function test403ReturnedIfUserIsNotAuthorizedForNova()
    {
        Nova::auth(function () {
            return false;
        });

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users');

        $response->assertStatus(403);
    }

    public function testHidesResourcesThatAreSoftDeleted()
    {
        factory(User::class)->create();
        $user = factory(User::class)->create();
        $deletedUser = factory(User::class)->create();
        $deletedUser->delete();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users');

        $this->assertEquals($user->id, $response->original['resources'][0]['id']->value);
        $this->assertEquals(2, $response->original['total']);

        $response->assertJsonCount(2, 'resources');
    }

    public function testCanListAResourceViaARelationship()
    {
        $user = factory(User::class)->create();
        $user->posts()->saveMany(factory(Post::class, 3)->create());
        factory(Post::class)->create();

        $user2 = factory(User::class)->create();

        // User that has posts...
        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts?viaResource=users&viaResourceId='.$user->id.'&viaRelationship=posts');

        $response->assertJsonCount(3, 'resources');
        $this->assertEquals(4, Post::count());

        // User that has no posts...
        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts?viaResource=users&viaResourceId='.$user2->id.'&viaRelationship=posts');

        $response->assertJsonCount(0, 'resources');
    }

    public function testCanListAResourceViaAManyToManyRelationship()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $user2 = factory(User::class)->create();

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.attachRole'] = true;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/roles?viaResource=users&viaResourceId='.$user->id.'&viaRelationship=roles');

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.attachRole']);

        $response->assertStatus(200);

        $this->assertEquals($role->id, $response->original['resources'][0]['id']->value);
        $this->assertTrue($response->original['resources'][0]['authorizedToUpdate']);
        $this->assertTrue($response->original['resources'][0]['authorizedToDelete']);
        $this->assertEquals(1, $response->original['total']);
    }

    public function testCanListAResourceViaAManyToManyRelationshipWithUnauthorizedInformation()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $user->roles()->attach($role);

        $user2 = factory(User::class)->create();

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.attachRole'] = false;
        $_SERVER['nova.user.detachRole'] = false;

        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/roles?viaResource=users&viaResourceId='.$user->id.'&viaRelationship=roles&relationshipType=belongsToMany');

        $this->assertEquals($user->id, $_SERVER['nova.user.detachRoleUser']->id);
        $this->assertEquals($role->id, $_SERVER['nova.user.detachRoleRole']->id);

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.attachRole']);
        unset($_SERVER['nova.user.detachRole']);

        $response->assertStatus(200);

        $this->assertEquals($role->id, $response->original['resources'][0]['id']->value);
        $this->assertFalse($response->original['resources'][0]['authorizedToUpdate']);
        $this->assertFalse($response->original['resources'][0]['authorizedToDelete']);
        $this->assertEquals(1, $response->original['total']);
    }

    public function testCanSearchForResources()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?search='.$user->email);

        $this->assertEquals($user->id, $response->original['resources'][0]['id']->value);
        $this->assertEquals(1, $response->original['total']);

        $response->assertJsonCount(1, 'resources');
    }

    public function testCanFilterResources()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        factory(User::class)->create();

        $filters = base64_encode(json_encode([
            [
                'class' => IdFilter::class,
                'value' => 2,
            ],
        ]));

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?filters='.$filters);

        $this->assertEquals(2, $response->original['resources'][0]['id']->value);
        $this->assertEquals(1, $response->original['total']);

        $response->assertJsonCount(1, 'resources');
    }

    public function testCanFilterResourcesWithACustomKey()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        factory(User::class)->create();

        $filters = base64_encode(json_encode([
            [
                'class' => (new CustomKeyFilter())->key(),
                'value' => 2,
            ],
        ]));

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?filters='.$filters);

        $this->assertEquals(2, $response->original['resources'][0]['id']->value);
        $this->assertEquals(1, $response->original['total']);

        $response->assertJsonCount(1, 'resources');
    }

    public function testFiltersCanHaveConstructorParameters()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        factory(User::class)->create();

        $filters = base64_encode(json_encode([
            [
                'class' => ColumnFilter::class,
                'value' => 2,
            ],
        ]));

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?filters='.$filters);

        $this->assertEquals(2, $response->original['resources'][0]['id']->value);
        $this->assertEquals(1, $response->original['total']);

        $response->assertJsonCount(1, 'resources');
    }

    public function testUnauthorizedFiltersAreNotApplied()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        factory(User::class)->create();

        $_SERVER['nova.idFilter.canSee'] = false;

        $filters = base64_encode(json_encode([
            [
                'class' => IdFilter::class,
                'value' => 2,
            ],
        ]));

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?filters='.$filters);

        unset($_SERVER['nova.idFilter.canSee']);

        $this->assertEquals(3, $response->original['total']);

        $response->assertJsonCount(3, 'resources');
    }

    public function testCanOrderResources()
    {
        $userB = factory(User::class)->create(['email' => 'b@b.com']);
        $userA = factory(User::class)->create(['email' => 'a@a.com']);
        $userC = factory(User::class)->create(['email' => 'c@c.com']);

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?orderBy=email&orderByDirection=asc');

        $response->assertJsonCount(3, 'resources');
        $this->assertEquals($userA->id, $response->original['resources'][0]['id']->value);

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?orderBy=email&orderByDirection=desc');

        $response->assertJsonCount(3, 'resources');
        $this->assertEquals($userC->id, $response->original['resources'][0]['id']->value);
        $this->assertEquals(3, $response->original['total']);
    }

    public function testCanLimitResourcesPerPage()
    {
        factory(User::class)->create();
        factory(User::class)->create();
        factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?perPage=2');

        $response->assertJsonCount(2, 'resources');
        $this->assertEquals(3, $response->original['total']);
    }

    public function testCanIncludeSoftDeletedResources()
    {
        factory(User::class)->create();
        $user = factory(User::class)->create();
        $deletedUser = factory(User::class)->create();
        $deletedUser->delete();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?trashed=with');

        $this->assertEquals($deletedUser->id, $response->original['resources'][0]['id']->value);

        $response->assertJsonCount(3, 'resources');
        $this->assertEquals(3, $response->original['total']);
    }

    public function testCanShowOnlySoftDeletedResources()
    {
        factory(User::class)->create();
        $user = factory(User::class)->create();
        $deletedUser = factory(User::class)->create();
        $deletedUser->delete();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users?trashed=only');

        $this->assertEquals($deletedUser->id, $response->original['resources'][0]['id']->value);

        $response->assertJsonCount(1, 'resources');
        $this->assertEquals(1, $response->original['total']);
    }

    public function testForbiddenResourceCantBeAccessed()
    {
        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/forbidden-users');

        $response->assertStatus(403);

        $_SERVER['nova.authorize.forbidden-users'] = true;

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/forbidden-users');

        $response->assertStatus(200);
    }

    public function testEagerRelationsLoad()
    {
        $post1 = factory(Post::class)->create();

        factory(Comment::class, 6)->create()->each(function ($comment) use ($post1) {
            $comment->commentable()->associate($post1);
        });

        DB::enableQueryLog();
        DB::flushQueryLog();

        // Eager-loading of the comment's author relation is not enabled.
        $response = $this->withoutExceptionHandling()
            ->getJson('/nova-api/comments');

        $response->assertStatus(200);
        $this->assertEquals(6, $response->original['total']);

        $this->assertEquals(14, count(DB::getQueryLog()));

        // Enable eager-loading of the comment's author relation.
        DB::flushQueryLog();
        $_SERVER['nova.comments.useEager'] = true;

        $response = $this->withExceptionHandling()
            ->getJson('/nova-api/comments');

        $response->assertStatus(200);
        $this->assertEquals(6, $response->original['total']);

        $this->assertEquals(4, count(DB::getQueryLog()));

        unset($_SERVER['nova.comments.useEager']);

        DB::disableQueryLog();
    }

    public function testCorrectlyFiltersIndexPivotFields()
    {
        $_SERVER['nova.roles.hidingAdminPivotField'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->roles()->attach($role);

        $queryString = http_build_query([
            'viaResource'      => 'users',
            'viaResourceId'    => $user->id,
            'viaRelationship'  => 'roles',
            'relationshipType' => 'belongsToMany',
        ]);

        $response = $this->withoutExceptionHandling()
            ->getJson('/nova-api/roles?'.$queryString)
            ->assertOk();

        tap(collect($response->original['resources'][0]['fields']), function ($fields) {
            $this->assertCount(3, $fields);
            $this->assertEmpty($fields->where('attribute', 'admin')->all());
        });

        unset($_SERVER['nova.roles.hidingAdminPivotField']);
    }

    public function testCorrectlyFiltersIndexPivotFieldsOfReverseRelations()
    {
        $_SERVER['nova.roles.hidingAdminPivotField'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->roles()->attach($role);

        $queryString = http_build_query([
            'viaResource'      => 'roles',
            'viaResourceId'    => $role->id,
            'viaRelationship'  => 'users',
            'relationshipType' => 'belongsToMany',
        ]);

        $response = $this->withoutExceptionHandling()
            ->getJson('/nova-api/users?'.$queryString)
            ->assertOk();

        tap(collect($response->original['resources'][0]['fields']), function ($fields) {
            $this->assertCount(7, $fields);
            $this->assertEmpty($fields->where('attribute', 'admin')->all());
        });

        unset($_SERVER['nova.roles.hidingAdminPivotField']);
    }

    public function testPivotFieldValuesAreResolvedCorrectly()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $user->roles()->attach($role, ['admin' => true]);

        $this->assertEquals(1, $user->roles->first()->pivot->admin);

        $queryString = http_build_query([
            'viaResource'      => 'users',
            'viaResourceId'    => $user->id,
            'viaRelationship'  => 'roles',
            'relationshipType' => 'belongsToMany',
        ]);

        $response = $this->withoutExceptionHandling()
            ->getJson('/nova-api/roles?'.$queryString)
            ->assertOk();

        tap(collect($response->original['resources'][0]['fields']), function ($fields) {
            $this->assertEquals(1, $fields->where('attribute', 'admin')->first()->value);
        });
    }

    public function testResourceIndexCanShowColumnBorders()
    {
        $_SERVER['nova.user.showColumnBorders'] = true;

        $resource = collect(Nova::resourceInformation(NovaRequest::create('/')))
            ->first(function ($resource) {
                return 'users' == $resource['uriKey'];
            });

        $this->assertTrue($resource['showColumnBorders']);
        unset($_SERVER['nova.users.showColumnBorders']);
    }

    public function testResourceIndexCanBeShownInTightStyle()
    {
        $_SERVER['nova.user.tableStyle'] = 'tight';

        $resource = collect(Nova::resourceInformation(NovaRequest::create('/')))
            ->first(function ($resource) {
                return 'users' == $resource['uriKey'];
            });

        $this->assertEquals('tight', $resource['tableStyle']);
        unset($_SERVER['nova.users.tableStyle']);
    }

    public function testResourceFieldsAreNotDuplicatedOnIndex()
    {
        $_SERVER['nova.showDuplicateField'] = true;

        factory(User::class)->create();

        $response = $this->withoutExceptionHandling()
            ->getJson('/nova-api/user-with-custom-fields')
            ->assertOk();

        $this->assertCount(1, collect($response->original['resources'][0]['fields'])->where('attribute', 'name'));

        unset($_SERVER['nova.showDuplicateField']);
    }
}
