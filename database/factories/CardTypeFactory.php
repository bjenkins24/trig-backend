<?php

namespace Database\Factories;

use App\Models\CardType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CardType::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => 'application/pdf',
        ];
    }
}
