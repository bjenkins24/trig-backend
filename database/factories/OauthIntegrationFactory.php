<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\OauthIntegration;
use Faker\Generator as Faker;

$factory->define(OauthIntegration::class, function (Faker $faker) {
    return [
        'name'            => 'google',
    ];
});
