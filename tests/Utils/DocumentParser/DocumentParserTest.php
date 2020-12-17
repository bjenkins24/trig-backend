<?php

namespace Tests\Utils\DocumentParser;

use App\Utils\DocumentParser\DocumentParser;
use App\Utils\Gpt3;
use Exception;
use Tests\TestCase;

class DocumentParserTest extends TestCase
{
    /**
     * @group n
     */
    public function testGetTagsSuccess(): void
    {
//        $this->mock(Gpt3::class, static function ($mock) {
//            $mock->shouldReceive('getEngine')->andReturn('babbage');
//            $mock->shouldReceive('complete')->andReturn([
//                'id'      => 'cmpl-kDXQjsjXU4Ng08GaJVU6svan',
//                'object'  => 'text_completion',
//                'created' => 1607731847,
//                'model'   => 'babbage:2020-05-03',
//                'choices' => [
//                    [
//                        'text' => <<<COMPLETION
        // Accountant, #Sales Enablement, Product Management
//
        //COMPLETION,
//                        'index'         => 0,
//                        'logprobs'      => null,
//                        'finish_reason' => 'max_tokens',
//                    ],
//                ],
//            ]);
//        });

        $documentText = <<<DOCUMENT_TEXT
What does it mean to be an entrepreneur? It's more than being a business owner; it's a perspective and a lifestyle.
The road to entrepreneurship is often a treacherous one filled with unexpected detours, roadblocks and dead ends. There are lots of sleepless nights, plans that don't work out, funding that doesn't come through and customers that never materialize. It can be so challenging to launch a business that it may make you wonder why anyone willingly sets out on such a path.

Despite all of these hardships, every year, thousands of entrepreneurs embark on this journey determined to bring their vision to fruition and fill a need they see in society. They open brick-and-mortar businesses, launch tech startups or bring a new product or service into the marketplace.
DOCUMENT_TEXT;

        $results = app(DocumentParser::class)->getTags($documentText);
        dd($results);
        $expectedTags = collect(['Accounting', 'Sales', 'Sales Enablement', 'Product Management']);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpFail(): void
    {
        $this->mock(Gpt3::class, static function ($mock) {
            $mock->shouldReceive('getEngine')->andReturn('babbage');
            $mock->shouldReceive('complete')->andThrow(new Exception('Fail!'));
        });

        $results = app(DocumentParser::class)->getTags('my text');
        $expectedTags = collect([]);
        self::assertEquals($expectedTags, $results);
    }

    public function testGtpNoResults(): void
    {
        $this->mock(Gpt3::class, static function ($mock) {
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
        $this->mock(Gpt3::class, static function ($mock) {
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
