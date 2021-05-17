<?php

namespace Tests\Feature\Controllers;

use App\Models\Collection;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\Collection\CollectionSerializer;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use Tests\TestCase;

class CollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws JsonException
     */
    public function testCreateCollection(): void
    {
        $linkShareType = 'public';
        $linkShareCapability = 'reader';
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'slug'        => 'my-cool-slug',
            'user_id'     => 1,
        ];
        $response = $this->client('POST', 'collection', array_merge($data, [
            'permissions' => [$linkShareType => $linkShareCapability],
        ]));

        $id = $this->getResponseData($response)->get('id');
        // Check if the response returns an id
        self::assertNotEmpty($id);
        // Check if the response returns a token
        self::assertNotEmpty($this->getResponseData($response)->get('token'));

        $this->assertDatabaseHas('link_share_settings', [
          'shareable_type'       => Collection::class,
          'shareable_id'         => $id,
          'link_share_type_id'   => app(LinkShareTypeRepository::class)->get($linkShareType)->id,
          'capability_id'        => app(CapabilityRepository::class)->get($linkShareCapability)->id,
        ]);

        $this->assertDatabaseHas('collections', $data);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCollection(): void
    {
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'slug'        => 'my-cool-slug',
        ];
        $response = $this->client('POST', 'collection', array_merge($data,
            ['permissions' => ['public' => 'writer']]
        ));
        $id = $this->getResponseData($response)->get('id');

        $this->assertDatabaseHas('link_share_settings', [
            'shareable_type'       => Collection::class,
            'shareable_id'         => $id,
            'link_share_type_id'   => app(LinkShareTypeRepository::class)->get('public')->id,
            'capability_id'        => app(CapabilityRepository::class)->get('writer')->id,
        ]);

        $newData = [
            'title'       => 'sup',
            'description' => 'so brow',
            'slug'        => 'new-slug',
            'user_id'     => 10,
        ];
        $response = $this->client('PATCH', "collection/$id", array_merge($newData,
            ['permissions' => ['anyoneWithLink' => 'reader']]
        ));

        $this->assertDatabaseMissing('link_share_settings', [
            'shareable_type'       => Collection::class,
            'shareable_id'         => $id,
            'link_share_type_id'   => app(LinkShareTypeRepository::class)->get('public')->id,
            'capability_id'        => app(CapabilityRepository::class)->get('writer')->id,
        ]);

        $this->assertDatabaseHas('link_share_settings', [
            'shareable_type'       => Collection::class,
            'shareable_id'         => $id,
            'link_share_type_id'   => app(LinkShareTypeRepository::class)->get('anyoneWithLink')->id,
            'capability_id'        => app(CapabilityRepository::class)->get('reader')->id,
        ]);

        // Check if the response returns an id
        self::assertEquals($this->getResponseData($response)->get('id'), $id);
        // Check if the response returns a token
        self::assertNotEmpty($this->getResponseData($response)->get('token'));

        // You can't change the user id with the update endpoint
        $newData['user_id'] = 1;
        $this->assertDatabaseHas('collections', $newData);
    }

    /**
     * @throws JsonException
     */
    public function testGetCollection(): void
    {
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'slug'        => 'my-cool-slug',
            'permissions' => ['public' => 'reader'],
        ];
        $response = $this->client('POST', 'collection', $data);
        $id = $this->getResponseData($response)->get('id');

        $response = $this->client('GET', "collection/$id");

        // Check if the response returns an id
        self::assertEquals($this->getResponseData($response)->get('id'), $id);
        // Check if the response returns a token
        self::assertNotEmpty($this->getResponseData($response)->get('token'));

        self::assertEquals(
            app(CollectionSerializer::class)->serialize(Collection::find($id)),
            json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCollection(): void
    {
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'slug'        => 'my-cool-slug',
        ];
        $response = $this->client('POST', 'collection', $data);
        $id = $this->getResponseData($response)->get('id');

        $response = $this->client('DELETE', "collection/$id");
        self::assertEquals(204, $response->getStatusCode());

        $this->assertDatabaseMissing('collections', [
            'id' => $id,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testGetCollectionForbidden(): void
    {
        $response = $this->client('GET', 'collection/1');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testPatchCollectionForbidden(): void
    {
        $response = $this->client('PATCH', 'collection/1');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCollectionForbidden(): void
    {
        $response = $this->client('DELETE', 'collection/1');
        self::assertEquals('forbidden', $this->getResponseData($response, 'error')->get('error'));
        self::assertEquals(403, $response->getStatusCode());
    }
}
