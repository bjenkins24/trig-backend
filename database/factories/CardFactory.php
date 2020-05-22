<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Card::class, function (Faker $faker) {
    return [
        'user_id'            => factory(User::class)->create()->id,
        'card_type_id'       => factory(CardType::class)->create()->id,
        'title'              => $faker->realText(rand(10, 50)),
        'description'        => $faker->realText(rand(50, 150)),
        'image'              => $faker->imageUrl(640, 480),
        'url'                => $faker->url,
        'actual_created_at'  => $faker->dateTime('2020-04-26 12:00:00'),
        'actual_modified_at' => $faker->dateTime('2020-04-26 14:00:00'),
    ];
});
