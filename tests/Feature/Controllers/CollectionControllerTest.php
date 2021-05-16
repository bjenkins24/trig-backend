<?php

namespace Tests\Feature\Controllers;

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
        $data = [
            'title'       => 'Google',
            'description' => 'content',
            'slug'        => 'my-cool-slug',
            'user_id'     => 1,
        ];
        $response = $this->client('POST', 'collection', $data);

        // Check if the response returns an id
        self::assertNotEmpty($this->getResponseData($response)->get('id'));
        // Check if the response returns a token
        self::assertNotEmpty($this->getResponseData($response)->get('token'));

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
        $response = $this->client('POST', 'collection', $data);
        $id = $this->getResponseData($response)->get('id');

        $newData = [
            'title'       => 'sup',
            'description' => 'so brow',
            'slug'        => 'new-slug',
            'user_id'     => 10,
        ];
        $response = $this->client('PATCH', "collection/$id", $newData);

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
        ];
        $response = $this->client('POST', 'collection', $data);
        $id = $this->getResponseData($response)->get('id');

        $response = $this->client('GET', "collection/$id");

        // Check if the response returns an id
        self::assertEquals($this->getResponseData($response)->get('id'), $id);
        // Check if the response returns a token
        self::assertNotEmpty($this->getResponseData($response)->get('token'));

        $this->assertDatabaseHas('collections', $data);
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
