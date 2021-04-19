<?php

namespace App\Utils;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class Gpt3
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
     * @return int|null 0 - Safe, 1 - Sensitive, 2 - Unsafe [https://beta.openai.com/docs/engines/content-filter]
     */
    public function getFilterLevel(string $prompt): ?int
    {
        try {
            $options = [
                'max_tokens'  => 1,
                'temperature' => 0.0,
                'top_p'       => 0,
                'logprobs'    => 10,
                'prompt'      => '<|endoftext|>['.$prompt.']\n--\nLabel:',
            ];
            $response = Http::retry(3, 1000)->withOptions([
                'connect_timeout' => 5,
                'timeout'         => 10,
                'headers'         => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer '.Config::get('app.gpt3_api_key'),
                ],
            ])->post('https://api.openai.com/v1/engines/content-filter-alpha-c4/completions', $options);
        } catch (RequestException $exception) {
            Log::error('GPT has failed to load: '.$exception->getMessage());

            return null;
        } catch (Exception $exception) {
            Log::error('There was unexpected problem with the response from GTP3: '.$exception->getMessage());

            return null;
        }

        $toxicThreshold = -0.355;

        try {
            $response = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('The json from GPT-3 could not be decoded: '.$exception->getMessage());

            return null;
        }

        if (! isset($response['choices'][0]['text'])) {
            Log::notice('There was an empty response from GPT-3: '.json_encode($response));

            return null;
        }

        if ('2' !== $response['choices'][0]['text']) {
            return (int) $response['choices']['0']['text'];
        }

        $logprobs = $response['choices']['0']['logprobs']['top_logprobs'][0];
        if ($logprobs[2] < $toxicThreshold) {
            $logProb0 = $logprobs[0] ?? null;
            $logProb1 = $logprobs[1] ?? null;
            if ($logProb0 && $logProb1) {
                if ($logProb0 >= $logProb1) {
                    return 0;
                }

                return 1;
            }
        }

        return 2;
    }

    public function complete(string $prompt, array $options, int $engineId = 1): ?array
    {
        $options['prompt'] = $prompt;

        $engine = $this->getEngine($engineId);
        try {
            $response = Http::retry(3, 1000)->withOptions([
                'connect_timeout' => 5,
                'timeout'         => 10,
                'headers'         => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer '.Config::get('app.gpt3_api_key'),
                ],
            ])->post("https://api.openai.com/v1/engines/$engine/completions", $options);
        } catch (RequestException $exception) {
            Log::error('GPT has failed to load: '.$exception->getMessage());

            return null;
        } catch (Exception $exception) {
            Log::error('There was unexpected problem with the response from GTP3: '.$exception->getMessage());

            return null;
        }

        try {
            $response = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('The json from GPT-3 could not be decoded: '.$exception->getMessage());

            return null;
        }

        if (empty($response['choices']) || empty($response['choices'][0]) || empty($response['choices'][0]['text'])) {
            Log::notice('There was an empty response from GPT-3: '.json_encode($response));

            return [];
        }

        return $response;
    }
}
