<?php

namespace App\Console\Commands;

use App\Jobs\SyncCards;
use App\Modules\OauthConnection\OauthConnectionRepository;
use Illuminate\Console\Command;

class CardSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'card:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync card integrations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        app(OauthConnectionRepository::class)->getAllActiveConnections()->each(static function ($connection) {
            SyncCards::dispatch($connection['user_id'], $connection['key'], strtotime('-2 hours'));
        });
    }
}
