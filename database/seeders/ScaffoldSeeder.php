<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\Collection;
use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class ScaffoldSeeder extends Seeder
{
    /**
     * Main user account.
     *
     * @var User
     */
    protected $user;

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function run(): void
    {
        if ('production' === env('APP_ENV')) {
            throw new Exception('The scaffolding seeder cannot be run on production.');
        }

        $user = User::where('email', \Config::get('constants.seed.email'))->first();
        if (! $user) {
            $user = User::factory()->create([
                'first_name' => Config::get('constants.seed.first_name'),
                'last_name'  => Config::get('constants.seed.last_name'),
                'email'      => Config::get('constants.seed.email'),
                'password'   => bcrypt(Config::get('constants.seed.password')),
            ]);
            $user->workspaces()->firstOrCreate([
                'name' => 'Trig',
            ]);
        }

        Card::factory()->create([
            'user_id'    => $user->id,
            'content'    => Config::get('constants.seed.card.content'),
        ]);

        Collection::factory()->create([
            'user_id'    => 2,
        ]);

        Card::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        // The first card is a google integrated card
        CardIntegration::factory()->create([
            'card_id' => 1,
        ]);
    }
}
