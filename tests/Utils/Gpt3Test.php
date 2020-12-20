<?php

namespace Tests\Utils;

use App\Utils\Gpt3;
use Illuminate\Support\Facades\Http;
use JsonException;
use Tests\TestCase;

class Gpt3Test extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testComplete(): void
    {
        $mockedResult = [
            'id'      => 'cmpl-kDXQjsjXU4Ng08GaJVU6svan',
            'object'  => 'text_completion',
            'created' => 1607731847,
            'model'   => 'babbage:2020-05-03',
            'choices' => [
                [
                    'text' => <<<COMPLETION
drip irrigation, sprinkler system for sure, water waste\n
\n
\n
Neuro-linguistic programming (NLP) is a
COMPLETION,
                    'index'         => 0,
                    'logprobs'      => null,
                    'finish_reason' => 'max_tokens',
                ],
            ],
        ];
        Http::fake(static function () use ($mockedResult) {
            return Http::response(json_encode($mockedResult, JSON_THROW_ON_ERROR), 200);
        });
        $result = app(Gpt3::class)->complete('My cool prompt', []);
        self::assertEquals($mockedResult, $result);

        Http::assertSent(static function ($request) {
            return 'https://api.openai.com/v1/engines/babbage/completions' === $request->url();
        });

        app(Gpt3::class)->complete('My cool prompt', [], 3);

        Http::assertSent(static function ($request) {
            return 'https://api.openai.com/v1/engines/davinci/completions' === $request->url();
        });
    }

    public function testEngineDoesntExist(): void
    {
        try {
            app(Gpt3::class)->getEngine(4);
            self::assertTrue(false);
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
    }
}
