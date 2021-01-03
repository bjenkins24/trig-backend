<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Faker\Generator as Faker;

$factory->define(Card::class, static function (Faker $faker) {
    $token = bin2hex(random_bytes(24));

    return [
        'user_id'                => factory(User::class)->create()->id,
        'workspace_id'           => factory(Workspace::class)->create()->id,
        'token'                  => $token,
        'card_type_id'           => factory(CardType::class)->create()->id,
        'title'                  => $faker->realText(random_int(10, 50)),
        'description'            => $faker->realText(random_int(50, 150)),
        'properties'             => ['thumbnail' => 'https://coolstuff.com/public/'.ThumbnailHelper::IMAGE_FOLDER.'/thumbnail/'.$token.'.jpg'],
        'url'                    => $faker->url,
        'content'                => $faker->realText(random_int(200, 400)),
        'actual_created_at'      => $faker->dateTime('2020-04-26 12:00:00'),
        'actual_updated_at'      => $faker->dateTime('2020-04-26 14:00:00'),
    ];
});
