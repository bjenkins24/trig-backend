<?php

namespace App\Support\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Passport\Client;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait HandlesAuth
{
    /**
     * Send internal authentication request.
     *
     * @return array
     */
    private function authResponse(array $args)
    {
        Arr::set($args, 'username', $args['email']);

        $data = array_merge($args, [
            'grant_type' => 'password',
            'scope'      => '',
        ]);

        $request = Request::create('oauth/token', 'POST', $data, [], [], [
            'HTTP_Accept' => 'application/json',
        ]);

        $response = app()->handle($request);

        return json_decode($response->getContent(), true);
    }

    /**
     * @throws Exception
     */
    public function authRequest(array $input): array
    {
        $client = Client::where('name', 'like', '%Password%')->first();

        if (! $client) {
            throw new RuntimeException("No oauth client found for passport. You likely didn't install passport on the server. You need to ssh in and run `php artisan passport:install`. This needs to happen only once. It adds an oauth client to the DB.");
        }

        $response = $this->authResponse(array_merge($input, [
            'client_secret' => $client->secret,
            'client_id'     => $client->getKey(),
        ]));

        if (Arr::has($response, 'error')) {
            throw new HttpException(200, $response['error']);
        }

        return $response;
    }
}
