<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\Card\CardRepository;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CardDedupe implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Card $card;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Card $card)
    {
        $this->card = $card;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            app(CardRepository::class)->dedupe($this->card);
        } catch (Exception $e) {
            // no-op
        }
    }
}
