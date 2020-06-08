<?php

use App\Models\Card;
use App\Models\CardIntegration;
use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Seeder;

class ScaffoldSeeder extends Seeder
{
    /**
     * Main user account.
     *
     * @var User
     */
    protected $user;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Factory::create();

        if ('production' === env('APP_ENV')) {
            throw new \Exception('The scaffolding seeder cannot be run on production.');
        }

        $user = User::where('email', \Config::get('constants.seed.email'))->first();
        if (! $user) {
            $user = factory(User::class)->create([
                'first_name' => \Config::get('constants.seed.first_name'),
                'last_name'  => \Config::get('constants.seed.last_name'),
                'email'      => \Config::get('constants.seed.email'),
                'password'   => bcrypt(\Config::get('constants.seed.password')),
            ]);
            $user->organizations()->firstOrCreate([
                'name' => 'Trig',
            ]);
        }
        factory(Card::class, 1)->create([
            'user_id'    => $user->id,
            'content'    => \Config::get('constants.seed.card.content'),
            'properties' => json_encode(['title' => \Config::get('constants.seed.card.doc_title')]),
        ]);

        factory(Card::class, 3)->create([
            'user_id' => $user->id,
        ]);

        // The first card is a google integrated card
        factory(CardIntegration::class)->create([
            'card_id' => 1,
        ]);
    }
}
