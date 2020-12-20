<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\CardTag\CardTagRepository;
use App\Utils\TagParser\TagParser;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GetTags implements ShouldQueue
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
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            $tags = app(TagParser::class)->getTags($this->card->title, Str::htmlToMarkdown($this->card->content));
            app(CardTagRepository::class)->replaceTags($this->card, $tags->toArray());
        } catch (Exception $e) {
            Log::error('The GetTags job failed: '.$e->getMessage());
        }
    }
}