<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class AttachableControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanRetrieveAttachableResources()
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users/'.$user->id.'/attachable/roles');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 1, 'display' => 1],
        ], $response->original['resources']->all());

        $this->assertFalse($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);
    }

    public function testCanRetrieveAttachableResourcesWithCustomRelationKey()
    {
        $_SERVER['nova.useRolesCustomAttribute'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $response = $this->withoutExceptionHandling()
            ->getJson('/nova-api/users/'.$user->id.'/attachable/roles');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 1, 'display' => 1],
        ], $response->original['resources']->all());

        $this->assertFalse($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);

        unset($_SERVER['nova.useRolesCustomAttribute']);
    }

    public function testCanRetrieveAttachableResourcesBySearch()
    {
        $user = factory(User::class)->create();

        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users/'.$user->id.'/attachable/roles?search='.$role2->name);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());

        $this->assertFalse($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);
    }

    public function testOnlyTheFirstMatchingRecordMayBeRetrieved()
    {
        $user = factory(User::class)->create();

        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/users/'.$user->id.'/attachable/roles?current='.$role2->id.'&first=true');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());

        $this->assertFalse($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);
    }

    public function testCanRetrieveAttachableResourcesWithSameRelationModel()
    {
        factory(User::class)->create();
        $post = factory(Post::class)->create();

        $response = $this->withExceptionHandling()
            ->getJson('/nova-api/posts/'.$post->id.'/attachable/users');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 1, 'display' => 1],
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());
    }
}
