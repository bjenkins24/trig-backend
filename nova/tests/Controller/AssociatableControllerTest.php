<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserResource;
use Laravel\Nova\Tests\IntegrationTest;

class AssociatableControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanRetrieveAssociatableResources()
    {
        $user = factory(User::class, 2)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts/associatable/user');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 1, 'display' => 1],
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());

        $this->assertTrue($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);
    }

    public function testCanRetrieveAssociatableResourcesViaSearch()
    {
        UserResource::$search = ['id'];

        $user = factory(User::class, 2)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts/associatable/user?search=2');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());

        $this->assertTrue($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);

        UserResource::$search = ['id', 'name', 'email'];
    }

    public function testOnlyTheFirstMatchingRecordMayBeRetrieved()
    {
        $user = factory(User::class, 2)->create();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts/associatable/user?current=2&first=true');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());

        $this->assertTrue($response->original['softDeletes']);
        $this->assertFalse($response->original['withTrashed']);
    }

    public function testSoftDeletedRecordsAreExcludedByDefault()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user2->delete();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts/associatable/user');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 1, 'display' => 1],
        ], $response->original['resources']->all());

        $this->assertCount(1, $response->original['resources']->all());
    }

    public function testSoftDeletedRecordsMayBeIncluded()
    {
        $user = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user2->delete();

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/posts/associatable/user?withTrashed=true');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'resources', 'softDeletes', 'withTrashed',
        ]);

        $this->assertEquals([
            ['value' => 1, 'display' => 1],
            ['value' => 2, 'display' => 2],
        ], $response->original['resources']->all());

        $this->assertCount(2, $response->original['resources']->all());
    }
}
