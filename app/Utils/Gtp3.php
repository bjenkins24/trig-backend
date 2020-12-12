<?php

namespace App\Utils;

use Illuminate\Support\Facades\Http;
use JsonException;

class Gtp3
{
    /**
     * @throws JsonException
     */
    public function complete(string $prompt, array $options, string $engine = 'babbage'): array
    {
        $options['prompt'] = $prompt;

        $response = Http::withOptions([
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer sk-tgCc9cW33t9UTPIZzplRZNNC2xfXkuS8f6tnId9h',
            ],
        ])->post("https://api.openai.com/v1/engines/$engine/completions", $options);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
