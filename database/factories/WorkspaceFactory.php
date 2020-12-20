<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\Workspace;
use Faker\Generator as Faker;

$factory->define(Workspace::class, function (Faker $faker) {
    return [
        'name' => $faker->company,
    ];
});
