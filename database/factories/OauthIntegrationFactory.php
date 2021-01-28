<?php

namespace Database\Factories;

use App\Models\OauthIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

class OauthIntegrationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OauthIntegration::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => 'google',
        ];
    }
}
