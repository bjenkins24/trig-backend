<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\OauthIntegration;
use Faker\Generator as Faker;

$factory->define(CardIntegration::class, function (Faker $faker) {
    return [
        'card_id'                    => factory(Card::class)->create()->id,
        'oauth_integration_id'       => factory(OauthIntegration::class)->create()->id,
        'foreign_id'                 => $faker->uuid,
    ];
});
