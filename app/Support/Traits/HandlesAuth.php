<?php

namespace App\Support\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Passport\Client;

trait HandlesAuth
{
    /**
     * Send internal authentication request.
     *
     * @return array
     */
    private function authResponse(array $args)
    {
        $data = array_merge($args, [
            'username'   => $args['email'],
            'grant_type' => 'password',
            'scope'      => '',
        ]);

        $request = Request::create('oauth/token', 'POST', $data, [], [], [
            'HTTP_Accept' => 'application/json',
        ]);

        // TODO: Catch potential exception
        $response = app()->handle($request);

        return json_decode($response->getContent(), true);
    }

    /**
     * Send authentication request.
     *
     * @return array
     */
    private function authRequest(array $input)
    {
        $client = Client::where('name', 'like', '%Password%')->first();

        $response = $this->authResponse(array_merge($input, [
            'client_secret' => $client->secret,
            'client_id'     => $client->getKey(),
        ]));

        if (Arr::has($response, 'error')) {
            echo $response['error'];
        }

        return $response;
    }
}
