<?php

use App\Models\Card;
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
            throw new \Exception('The scaffolding seeder can not be run on production.');
        }

        $user = User::where('email', \Config::get('constants.seed.email'))->first();
        if (! $user) {
            $user = factory(User::class)->create([
                'first_name' => \Config::get('constants.seed.first_name'),
                'last_name'  => \Config::get('constants.seed.last_name'),
                'email'      => \Config::get('constants.seed.email'),
                'password'   => bcrypt(\Config::get('constants.seed.password')),
            ]);
        }
        factory(Card::class, 3)->create([
            'user_id' => $user->id,
        ]);
    }
}
