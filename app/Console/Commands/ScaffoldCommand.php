<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Traits\HandlesAuth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Laravel\Passport\Client;

class ScaffoldCommand extends Command
{
    use HandlesAuth;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scaffold';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffold a new application.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ('production' === Config::get('app.env')) {
            $this->error('Cannot scaffold application in production environment');

            return;
        }

        if ('testing' !== Config::get('app.env')) {
            $this->call('elastic:migrate:reset');
        }
        $this->call('migrate:fresh');
        $this->call('passport:install');
        if ('testing' !== Config::get('app.env')) {
            $this->call('elastic:migrate');
        }

        $email = Config::get('constants.seed.email');
        $password = Config::get('constants.seed.password');

        $user = factory(User::class)->create([
            'first_name' => Config::get('constants.seed.first_name'),
            'last_name'  => Config::get('constants.seed.last_name'),
            'email'      => $email,
            'password'   => bcrypt($password),
        ]);

        $user->organizations()->create([
            'name' => Config::get('constants.seed.organization'),
        ]);

        $this->call('db:seed', ['--class' => 'ScaffoldSeeder']);

        if ('testing' === Config::get('app.env')) {
            $this->info('Testing environment successfully setup!');

            return;
        }
        $this->line('');
        $this->info('Application successfully setup!');
        $this->line('Username: '.Config::get('constants.seed.email'));
        $this->line('Password: '.Config::get('constants.seed.password'));

        $client = Client::where('name', 'like', '%Password%')->first();
        $response = $this->authResponse([
            'email'         => $email,
            'password'      => $password,
            'client_secret' => $client->secret,
            'client_id'     => $client->getKey(),
        ]);

        $this->line('');
        $this->line('');
        $this->line('Your Access Token:');
        $this->line(data_get($response, 'access_token'));
        $this->line('');
    }
}
