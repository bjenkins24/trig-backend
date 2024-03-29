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
        $hiddenTag1 = 'Product';
        $hiddenTag2 = 'Product/People';
        $linkShareType = 'public';
        $linkShareCapability = 'reader';
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'user_id'     => 1,
        ];
        $response = $this->client('POST', 'collection', array_merge($data, [
            'permissions' => [
                ['type' => $linkShareType, 'capability' => $linkShareCapability],
            ],
            'hidden_tags' => [$hiddenTag1, $hiddenTag2],
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

        $this->assertDatabaseHas('collection_hidden_tags', [
            'collection_id' => $id,
            'tag'           => $hiddenTag1,
        ]);
        $this->assertDatabaseHas('collection_hidden_tags', [
            'collection_id' => $id,
            'tag'           => $hiddenTag2,
        ]);
    }

    /**
     * @throws JsonException
     */
    public function testUpdateWithToken(): void
    {
        $response = $this->client('POST', 'collection', ['title' => 'hello']);
        $token = $this->getResponseData($response)->get('token');
        $response = $this->client('GET', 'collection/'.$token);
        self::assertEquals(200, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testGetWithToken(): void
    {
        $response = $this->client('POST', 'collection', ['title' => 'hello']);
        $token = $this->getResponseData($response)->get('token');
        $response = $this->client('PATCH', 'collection/'.$token, ['title' => 'hello']);
        self::assertEquals(200, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteWithToken(): void
    {
        $response = $this->client('POST', 'collection', ['title' => 'hello']);
        $token = $this->getResponseData($response)->get('token');
        $response = $this->client('DELETE', 'collection/'.$token);
        self::assertEquals(204, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testUpdateCollection(): void
    {
        $hiddenTag1 = 'My man';
        $hiddenTag2 = 'My girl';
        $data = [
            'title'       => 'Google',
            'description' => 'content',
        ];
        $response = $this->client('POST', 'collection', array_merge($data,
            [
                'permissions' => [
                    [
                        'type'        => 'public',
                        'capability'  => 'writer',
                    ],
                ],
                'hidden_tags' => [$hiddenTag1, $hiddenTag2],
            ]
        ));

        $id = $this->getResponseData($response)->get('id');

        $this->assertDatabaseHas('collection_hidden_tags', [
            'collection_id' => $id,
            'tag'           => $hiddenTag1,
        ]);
        $this->assertDatabaseHas('collection_hidden_tags', [
            'collection_id' => $id,
            'tag'           => $hiddenTag2,
        ]);

        $this->assertDatabaseHas('link_share_settings', [
            'shareable_type'       => Collection::class,
            'shareable_id'         => $id,
            'link_share_type_id'   => app(LinkShareTypeRepository::class)->get('public')->id,
            'capability_id'        => app(CapabilityRepository::class)->get('writer')->id,
        ]);

        $newData = [
            'title'       => 'sup',
            'description' => 'so brow',
            'user_id'     => 10,
        ];
        $response = $this->client('PATCH', "collection/$id", array_merge($newData,
            [
                'permissions' => [
                    ['type' => 'anyoneWithLink', 'capability' => 'reader'],
                ],
            ]
        ));

        $this->assertDatabaseMissing('collection_hidden_tags', [
            'collection_id' => $id,
            'tag'           => $hiddenTag1,
        ]);
        $this->assertDatabaseMissing('collection_hidden_tags', [
            'collection_id' => $id,
            'tag'           => $hiddenTag2,
        ]);

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

    public function testUpdateNotFound(): void
    {
        $response = $this->client('PATCH', 'collection/10');
        self::assertEquals(404, $response->getStatusCode());
    }

    public function testGetNotFound(): void
    {
        $response = $this->client('GET', 'collection/10');
        self::assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteNotFound(): void
    {
        $response = $this->client('DELETE', 'collection/10');
        self::assertEquals(404, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testGetCollection(): void
    {
        $hiddenTag1 = 'hello';
        $hiddenTag2 = 'goodbye';
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'permissions' => [
                ['type' => 'public', 'capability' => 'reader'],
            ],
            'hidden_tags' => [
                $hiddenTag1, $hiddenTag2,
            ],
        ];
        $response = $this->client('POST', 'collection', $data);
        $id = $this->getResponseData($response)->get('id');
        $this->client('PATCH', 'card/1', ['collections' => [$id]]);

        $response = $this->client('GET', "collection/$id");
        $data = $this->getResponseData($response);

        // Check if the response returns an id
        self::assertEquals($data->get('id'), $id);
        // Check if the response returns a token
        self::assertNotEmpty($data->get('token'));
        self::assertEquals(1, $data->get('total_cards'));
        self::assertEquals([$hiddenTag1, $hiddenTag2], $data->get('hidden_tags'));

        self::assertEquals(
            ['data' => app(CollectionSerializer::class)->serialize(Collection::find($id))],
            json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @throws JsonException
     */
    public function testGetCollections(): void
    {
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'permissions' => [
                ['type' => 'public', 'capability' => 'reader'],
            ],
        ];
        $this->client('POST', 'collection', $data);
        $secondTitle = 'sup';
        $this->client('POST', 'collection', ['title' => $secondTitle]);

        $response = $this->client('GET', 'collections');
        $data = $this->getResponseData($response);
        self::assertEquals($secondTitle, $data->get(0)['title']);
        self::assertCount(2, $this->getResponseData($response)->toArray());
    }

    /**
     * @throws JsonException
     */
    public function testDeleteCollection(): void
    {
        $data = [
            'title'       => 'Google',
            'description' => 'content',
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
