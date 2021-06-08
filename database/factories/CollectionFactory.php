<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Collection::class;

    /**
     * Define the model's default state.
     *
     * @throws Exception
     *
     * @return array
     */
    public function definition()
    {
        $token = bin2hex(random_bytes(24));

        return [
            'user_id'     => User::factory()->create()->id,
            'token'       => $token,
            'title'       => $this->faker->realText(random_int(10, 50)),
            'description' => $this->faker->realText(random_int(50, 150)),
        ];
    }
}
