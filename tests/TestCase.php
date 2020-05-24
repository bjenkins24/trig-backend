<?php

namespace Tests;

use Artisan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function refreshDb()
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
}
