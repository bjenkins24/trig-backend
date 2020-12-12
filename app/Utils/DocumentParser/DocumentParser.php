<?php

namespace App\Utils\DocumentParser;

use App\Utils\Gtp3;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentParser
{
    private Gtp3 $gtp3;

    public function __construct(Gtp3 $gtp3)
    {
        $this->gtp3 = $gtp3;
    }

    public function getTags(string $documentText, $engineId = 1): Collection
    {
        if (! $documentText) {
            return collect([]);
        }

        $truncatedDocumentText = Str::truncateOnWord(Str::removeLineBreaks($documentText), 1600);
        $exampleTags = ['drip irrigation', 'sprinkler system', 'water waste', 'water runoff'];
        $table = implode(' | ', $exampleTags);

        $prompt = <<<PROMPT
**Drip irrigation** is a system of tubing that directs __small quantities__ of water precisely where it’s needed, preventing the water waste associated with sprinkler systems. Drip systems minimize water runoff, evaporation, and wind drift by delivering a slow, uniform stream of water either above the soil surface or directly to the root zone.

Make a table of keywords from markdown above
| $table


$truncatedDocumentText

Make a table of keywords from markdown above
|
PROMPT;

        try {
            $response = $this->gtp3->complete($prompt, [
                'max_tokens'        => 24,
                'temperature'       => 0.4,
                'top_p'             => 0,
                'frequency_penalty' => 1,
                'presence_penalty'  => 0.1,
            ], $engineId);
        } catch (Exception $exception) {
            Log::notice('There was a problem with the response from GTP3: '.$exception->getMessage());

            return collect([]);
        }

        if (empty($response['choices']) || empty($response['choices']['0']) || empty($response['choices'][0]['text'])) {
            Log::notice('There was a problem with the response from GTP3: '.json_encode($response));

            return collect([]);
        }

        $completion = $response['choices']['0']['text'];
        $potentialTags = explode('|', $completion);
        $tags = [];
        foreach ($potentialTags as $tag) {
            if (false !== strpos($tag, PHP_EOL)) {
                continue;
            }
            $tags[] = trim($tag);
        }

        $nextEngineNoticeMessage = '';
        if ($engineId < 3) {
            $nextEngineNoticeMessage = "Trying {$this->gtp3->getEngine($engineId + 1)} engine for tag generation. Got $completion from $truncatedDocumentText";
        }
        // If the result includes the example tags then the tag retrieval didn't work. Let's try a better engine
        foreach ($tags as $tagKey => $tag) {
            // Bad results tend to have 4 words or more in tags - but let's only go up to curie for this
            // since it _is_ possible to get 4 words legitimately
            if ($engineId < 2 && str_word_count($tag) > 3) {
                Log::notice($nextEngineNoticeMessage);

                return $this->getTags($documentText, $engineId + 1);
            }
            if (in_array($tag, $exampleTags, true)) {
                if (3 !== $engineId) {
                    Log::notice($nextEngineNoticeMessage);

                    return $this->getTags($documentText, $engineId + 1);
                }

                // We tried davinci and _still_ got the example tags. Let's just remove them. We tried our best
                // This also means nothing will _ever_ be tagged as our example tags. For now I think that's ok. I mean
                // What are the odds that we get sprinkler system in here? Even if we do, worst case is no one gets
                // articles tagged as sprinkler system. I can live with that
                unset($tags[$tagKey]);
            }
        }

        return collect($tags);
    }
}
