<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\OauthIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardIntegrationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CardIntegration::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'card_id'                    => Card::factory()->create()->id,
            'oauth_integration_id'       => OauthIntegration::factory()->create()->id,
            'foreign_id'                 => $this->faker->uuid,
        ];
    }
}
