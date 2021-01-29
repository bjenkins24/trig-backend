<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardType;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Helpers\ThumbnailHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Card::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $token = bin2hex(random_bytes(24));

        return [
            'user_id'                => User::factory()->create()->id,
            'workspace_id'           => Workspace::factory()->create()->id,
            'token'                  => $token,
            'card_type_id'           => CardType::factory()->create()->id,
            'title'                  => $this->faker->realText(random_int(10, 50)),
            'description'            => $this->faker->realText(random_int(50, 150)),
            'properties'             => ['thumbnail' => 'https://coolstuff.com/public/'.ThumbnailHelper::IMAGE_FOLDER.'/thumbnail/'.$token.'.jpg'],
            'url'                    => $this->faker->url,
            'content'                => $this->faker->realText(random_int(200, 400)),
            'actual_created_at'      => $this->faker->dateTime('2020-04-26 12:00:00'),
            'actual_updated_at'      => $this->faker->dateTime('2020-04-26 14:00:00'),
        ];
    }
}
