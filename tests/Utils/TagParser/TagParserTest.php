<?php

namespace Tests\Utils\TagParser;

use App\Utils\Gpt3;
use App\Utils\TagParser\TagParser;
use App\Utils\TagParser\TagStringRemoval;
use Exception;
use Tests\TestCase;

class TagParserTest extends TestCase
{
    private function mockResponse(string $completion): void
    {
        $this->mock(Gpt3::class, static function ($mock) use ($completion) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn([
                'id'      => 'cmpl-kDXQjsjXU4Ng08GaJVU6svan',
                'object'  => 'text_completion',
                'created' => 1607731847,
                'model'   => 'babbage:2020-05-03',
                'choices' => [
                    [
                        'text'          => $completion,
                        'index'         => 0,
                        'logprobs'      => null,
                        'finish_reason' => 'max_tokens',
                    ],
                ],
            ]);
        });
    }

    public function testRemoveConsecutive(): void
    {
        $tags = app(TagStringRemoval::class)->removeConsecutiveNumbers(['Audible', 'Audible2', 'Audible3']);
        self::assertEquals(['Audible'], $tags);

        $tags = app(TagStringRemoval::class)->removeConsecutiveNumbers(['Covid 19', 'Covid 20', 'Covid 21']);
        self::assertEquals(['Covid 19'], $tags);
    }

    public function testDocToBlock(): void
    {
        $result = app(TagParser::class)->docToBlock(<<<RAW_TEXT
In this article, I will start by
Doing a short introduction to what is GPT-3.
Then, we will see
Why and how GPT-3 can be useful: to you and your company with many real-world application examples.
Following that, I will cover
1. How you can get access to this API and why you are on a waitlist.

2. I will give some tips on getting accepted faster to this API and conclude with a video demonstration of these awesome applications made using this API.

What is GPT-3

GPT-3 is a new text-generating program from OpenAI. This model is pre-trained, but it is never touched again.
Specifically, they trained GPT-3 on a dataset of half a trillion words for 175 billion parameters, which is 10x more than any previous non-sparse language model.
Then, there is no more fine-tuning to do with this model, only few-shot demonstrations specified purely via text interaction with the model.  For example, an English sentence and its French translation.

The few-shot works by giving a certain amount of examples of context and completion, and then one final example of context, with the model expected to provide the completion without changing the model’s parameters.
The model even reaches competitiveness with prior state-of-the-art approaches that are directly fine-tuned on the specific task!In short, it works great because its memory pretty much contains all text ever published by humans on the internet.
RAW_TEXT, 1600
);
        self::assertEquals(<<<EXPECTED
In this article, I will start by. Doing a short introduction to what is GPT-Then, we will see. Why and how GPT-3 can be useful. to you and your company with many real-world application examples. Following that, I will cover. How you can get access to this API and why you are on a waitlist. I will give some tips on getting accepted faster to this API and conclude with a video demonstration of these awesome applications made using this API. What is GPT-GPT-3 is a new text-generating program from OpenAI. This model is pre-trained, but it is never touched again. Specifically, they trained GPT-3 on a dataset of half a trillion words for 175 billion parameters, which is 10x more than any previous non-sparse language model. Then, there is no more fine-tuning to do with this model, only few-shot demonstrations specified purely via text interaction with the model. For example, an English sentence and its French translation. The few-shot works by giving a certain amount of examples of context and completion, and then one final example of context, with the model expected to provide the completion without changing the model’s parameters. The model even reaches competitiveness with prior state-of-the-art approaches that are directly fine-tuned on the specific task! In short, it works great because its memory pretty much contains all text ever published by humans on the internet.
EXPECTED, $result
);
    }

    /**
     * @dataProvider tagSuccessProvider
     */
    public function testGetTagsSuccess(string $title, string $content, string $completion, array $expected): void
    {
        $this->mockResponse($completion);
        $results = app(TagParser::class)->getTags($title, $content);
        self::assertEquals(collect($expected), $results);
    }

    public function tagSuccessProvider(): array
    {
        return [
            [
                'fake',
                'fake',
                <<<COMPLETION
Accountant, #Sales Enablement, Product Management

COMPLETION,
                ['Accounting', 'Sales', 'Product Management', 'Sales Enablement'],
            ],
            [
                'Amazon.com: Books',
                'fake',
                <<<COMPLETION
~Cool Tag, Fan, Amazon.com, HR

COMPLETION,
                ['Human Resources', 'Book', 'Cool Tag', 'Fan', 'Amazon'],
            ],
            [
                'fake',
                'fake',
                <<<COMPLETION
Risk Managers, Making an MVP, Budget,

COMPLETION,
                ['Risk Management', 'MVP', 'Budgeting', 'Making an MVP'],
            ],
            [
                'fake',
                'fake',
                <<<COMPLETION
Covid 19, Covid 20, Covid 21, Do it yourself, it, cash, Cash, Cash Money, Covid 19

COMPLETION,
                ['Covid 19', 'DIY', 'Cash Money'],
            ],
        ];
    }

    public function testGtpFail(): void
    {
        $this->mock(Gpt3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andThrow(new Exception('Fail!'));
        });

        $results = app(TagParser::class)->getTags('my text', 'my document');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpNoResults(): void
    {
        $this->mock(Gpt3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn(['no results']);
        });

        $results = app(TagParser::class)->getTags('my text', 'hello');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpNoInput(): void
    {
        $results = app(TagParser::class)->getTags('', 'hello');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testIncreasingEngine(): void
    {
        $this->mock(Gpt3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn([
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
            // it will only run 2 times since we don't want to go all the way to davinci for four word tags
            // We end up just removing them - davinci throws out 4 word tags too
            ])->times(2);
        });

        $results = app(TagParser::class)->getTags('my text', 'document text', 'tag');
        $expectedTags = collect(['drip irrigation', 'water waste']);
        self::assertEquals($expectedTags, $results);
    }
}
