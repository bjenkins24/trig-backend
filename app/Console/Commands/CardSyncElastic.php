<?php

namespace App\Console\Commands;

use App\Models\Card;
use Illuminate\Console\Command;

class CardSyncElastic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'card-sync-elastic {page=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save all cards to elastic search';

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
     * @return int
     */
    public function handle()
    {
        Card::all()->each(static function (Card $card) {
            $card->save();
        });
    }
}
