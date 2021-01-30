<?php

namespace App\Jobs;

use App\Models\Card;
use App\Modules\CardTag\CardTagRepository;
use App\Modules\Tag\TagService;
use App\Utils\TagParser\TagHypernym;
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
    public function handle(): bool
    {
        try {
            $tags = app(TagParser::class)->getTags($this->card->title, Str::htmlToMarkdown($this->card->content), $this->card->url);
            $hypernyms = app(TagHypernym::class)->getHypernyms($tags->toArray());

            $hypernymTags = app(TagService::class)->useHypernyms($this->card->workspace_id, $hypernyms);
            $tags = $tags->merge($hypernymTags);
            $cardTagRepository = app(CardTagRepository::class);
            $cardTagRepository->addHypernymsToOldCards($tags, $this->card->workspace_id);
            $cardTagRepository->replaceTags($this->card, $tags->toArray(), $hypernyms->toArray());

            return true;
        } catch (Exception $e) {
            Log::error('The GetTags job failed: '.$e->getMessage());

            return false;
        }
    }
}
