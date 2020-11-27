<?php

namespace Tests;

use Artisan;
use Illuminate\Database\Query\Expression;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use JsonException;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function refreshDb(): void
    {
        Artisan::call('migrate:fresh');
        Artisan::call('passport:install');
        Artisan::call('db:seed', ['--class' => 'ScaffoldSeeder']);
    }

    public function client(
        string $method,
        string $endpoint,
        array $params = [],
        array $headers = []
    ): TestResponse {
        $response = $this->json('POST', 'login', [
            'email'    => \Config::get('constants.seed.email'),
            'password' => \Config::get('constants.seed.password'),
        ]);

        $token = \Arr::get($response->json(), 'data.authToken.access_token');

        return $this->withHeaders(array_merge($headers, [
            'Authorization' => 'Bearer '.$token,
        ]))->json($method, $endpoint, $params);
    }

    /**
     * @param JsonResponse|TestResponse $response
     *
     * @throws JsonException
     */
    protected function getResponseData($response, string $type = 'data'): Collection
    {
        if ('data' === $type || 'meta' === $type) {
            return collect(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)[$type]);
        }

        return collect(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param $json
     *
     * @throws JsonException
     */
    public function castToJson($json): Expression
    {
        // Convert from array to json and add slashes, if necessary.
        if (is_array($json)) {
            try {
                $json = addslashes(json_encode($json, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
            }
        }
        // Or check if the value is malformed.
        elseif (is_null($json) || is_null(json_decode($json, true, 512, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('A valid JSON string was not provided.');
        }

        return \DB::raw("CAST('{$json}' AS JSON)");
    }
}
