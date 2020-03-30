<?php

use App\Models\User;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class UserSeeder extends Seeder
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

        $user = User::where('email', Config::get('constants.seed.email'))->first();

        if (! $user) {
            factory(User::class, 1)->create([
                'email' => Config::get('constants.seed.email'),
            ]);
        }
    }
}