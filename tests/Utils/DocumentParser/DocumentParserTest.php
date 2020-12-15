<?php

namespace Tests\Utils\DocumentParser;

use App\Utils\DocumentParser\DocumentParser;
use App\Utils\Gtp3;
use Exception;
use Tests\TestCase;

class DocumentParserTest extends TestCase
{
    /**
     * @group n
     */
    public function testGetTagsSuccess(): void
    {
        $this->mock(Gtp3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn([
                'id'      => 'cmpl-kDXQjsjXU4Ng08GaJVU6svan',
                'object'  => 'text_completion',
                'created' => 1607731847,
                'model'   => 'babbage:2020-05-03',
                'choices' => [
                    [
                        'text' => <<<COMPLETION
 Accountant, #Sales Enablement, Product Management

COMPLETION,
                        'index'         => 0,
                        'logprobs'      => null,
                        'finish_reason' => 'max_tokens',
                    ],
                ],
            ]);
        });

        $documentText = <<<DOCUMENT_TEXT
Neuro-linguistic programming (NLP) is a psychological approach that involves analyzing strategies used by successful individuals and applying them to reach a personal goal. It relates thoughts, language, and patterns of behavior learned through experience to specific outcomes.

Proponents of NLP assume all human action is positive. Therefore, if a plan fails or the unexpected happens, the experience is neither good nor bad—it simply presents more useful information.

HISTORY OF NEURO-LINGUISTIC PROGRAMMING
Neuro-linguistic programming was developed in the 1970s at the University of California, Santa Cruz. Its primary founders are John Grinder, a linguist, and Richard Bandler, an information scientist and mathematician. Judith DeLozier and Leslie Cameron-Bandler also contributed significantly to the field, as did David Gordon and Robert Dilts.

Grinder and Bandler's first book on NLP, Structure of Magic: A Book about Language of Therapy, was released in 1975. In this publication, they attempted to highlight certain patterns of communication that set communicators considered to be excellent apart from others. Much of the book was based on the work of Virginia Satir, Fritz Perls, and Milton Erickson. It also integrated techniques and theories from other renowned mental health professionals and researchers such as Noam Chomsky, Gregory Bateson, Carlos Castaneda, and Alfred Korzybski. The result of Grinder and Bandler's work was the development of the NLP meta model, a technique they believed could identify language patterns that reflected basic cognitive processes.
DOCUMENT_TEXT;

        $results = app(DocumentParser::class)->getTags($documentText);
        $expectedTags = collect(['Accounting', 'Sales', 'Sales Enablement', 'Product Management']);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpFail(): void
    {
        $this->mock(Gtp3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andThrow(new Exception('Fail!'));
        });

        $results = app(DocumentParser::class)->getTags('my text');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpNoResults(): void
    {
        $this->mock(Gtp3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn(['no results']);
        });

        $results = app(DocumentParser::class)->getTags('my text');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testCleanTags()
    {
//        $tags = [
//            'customer feedback',
//            'Consistency',
//            'Product Managers',
//        ];
        $tags = [
            'Five Dangerous Myths',
            'Good Group Product Manager',
            'Bad Group Product Editor',
        ];
        $tags = app(DocumentParser::class)->cleanTags($tags);
        dd($tags);
    }

    public function testGtpSequential(): void
    {
        $this->mock(Gtp3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn(['no results']);
        });

        $results = app(DocumentParser::class)->getTags('my text');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpNoInput(): void
    {
        $results = app(DocumentParser::class)->getTags('');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testIncreasingEngine(): void
    {
        $this->mock(Gtp3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andReturn([
                'id'      => 'cmpl-kDXQjsjXU4Ng08GaJVU6svan',
                'object'  => 'text_completion',
                'created' => 1607731847,
                'model'   => 'babbage:2020-05-03',
                'choices' => [
                    [
                        'text' => <<<COMPLETION
drip irrigation | sprinkler system | water waste |\n
\n
\n
Neuro-linguistic programming (NLP) is a
COMPLETION,
                        'index'         => 0,
                        'logprobs'      => null,
                        'finish_reason' => 'max_tokens',
                    ],
                ],
            ])->times(3);
        });

        $results = app(DocumentParser::class)->getTags('my text');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }
}
