<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\CardType;
use Faker\Generator as Faker;

$factory->define(CardType::class, function (Faker $faker) {
    return [
        'name'            => 'application/pdf',
    ];
});
