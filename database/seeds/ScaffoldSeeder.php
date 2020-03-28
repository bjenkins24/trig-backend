<?php

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

        $user = User::where('email', 'john.doe@trytrig.com')->first();

        factory(Person::class, 10)->create([
            'user_id' => $user->getKey(),
        ]);
    }
}
