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

    public function getTags(string $documentText): Collection
    {
        $truncatedDocumentText = Str::truncateOnWord($documentText, 1600);

        $prompt = <<<PROMPT
A feature-less roadmap is a roadmap designed to function as a strategic blueprint. Feature-less roadmaps enable product managers to deliver a product that both solves customer problems and supports the broader goals of the company.

Make a table from keywords above
| product managers | feature-less roadmap | customer problems |


$truncatedDocumentText

Make a table from keywords above
|
PROMPT;

        try {
            $response = $this->gtp3->complete($prompt, [
                'max_tokens'        => 24,
                'temperature'       => 0.4,
                'top_p'             => 0,
                'frequency_penalty' => 1,
                'presence_penalty'  => 0.1,
            ]);
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

        // This is what gtp does when it doesn't know what to do
        if ($tags === collect(['product managers', 'feature-less roadmap', 'customer problems'])) {
            return collect([]);
        }

        return collect($tags);
    }
}
