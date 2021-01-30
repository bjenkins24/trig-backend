<?php

namespace Tests\Utils\TagParser;

use App\Utils\TagParser\TagHypernym;
use App\Utils\TagParser\TagPrompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagHypernymTest extends TestCase
{
    use RefreshDatabase;

    private function mockPrompt(string $completion): void
    {
        $this->mock(TagPrompts::class, static function ($mock) use ($completion) {
            $mock->shouldReceive('completeHypernym')->andReturn([
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

    public function testGetHypernyms(): void
    {
        $this->mockPrompt('Appliance');
        $tags = [
            0 => 'Refrigerator',
            1 => 'Mushroom Soup',
            3 => 'Dragonball Z',
            4 => 'Sales',
        ];
        $result = app(TagHypernym::class)->getHypernyms($tags);
        self::assertEquals(['Appliance', 'Appliance', '', 'Appliance'], $result->toArray());
    }

    public function testBadHypernym(): void
    {
        $this->mockPrompt('white');
        $tags = [
            0 => 'white egg',
        ];
        $result = app(TagHypernym::class)->getHypernyms($tags);
        self::assertEquals(['white'], $result->toArray());
    }

    public function testFailedCompletion(): void
    {
        $this->mock(TagPrompts::class, static function ($mock) {
            $mock->shouldReceive('completeHypernym')->andReturn([]);
        });

        $result = app(TagHypernym::class)->getHypernyms(['hello']);
        self::assertEquals([0 => ''], $result->toArray());
    }
}
