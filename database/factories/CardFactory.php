<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Card::class, static function (Faker $faker) {
    return [
        'user_id'            => factory(User::class)->create()->id,
        'token'              => bin2hex(random_bytes(24)),
        'card_type_id'       => factory(CardType::class)->create()->id,
        'title'              => $faker->realText(rand(10, 50)),
        'description'        => $faker->realText(rand(50, 150)),
        'image'              => $faker->imageUrl(640, 480),
        'url'                => $faker->url,
        'content'            => $faker->realText(rand(200, 400)),
        'actual_created_at'  => $faker->dateTime('2020-04-26 12:00:00'),
        'actual_updated_at'  => $faker->dateTime('2020-04-26 14:00:00'),
    ];
});
