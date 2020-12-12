<?php

namespace App\Utils;

use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class Gtp3
{
    public function getEngine(int $id): string
    {
        $engines = ['ada', 'babbage', 'curie', 'davinci'];
        if (! isset($engines[$id])) {
            throw new RuntimeException('0-3 are the only valid engine ids you entered '.$id);
        }

        return $engines[$id];
    }

    /**
     * @throws JsonException
     */
    public function complete(string $prompt, array $options, int $engineId = 1): array
    {
        $options['prompt'] = $prompt;

        $engine = $this->getEngine($engineId);
        $response = Http::withOptions([
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer sk-tgCc9cW33t9UTPIZzplRZNNC2xfXkuS8f6tnId9h',
            ],
        ])->post("https://api.openai.com/v1/engines/$engine/completions", $options);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
