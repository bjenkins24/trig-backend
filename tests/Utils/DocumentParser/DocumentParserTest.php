<?php

namespace Tests\Utils\DocumentParser;

use App\Utils\DocumentParser\DocumentParser;
use App\Utils\Gpt3;
use Exception;
use Tests\TestCase;

class DocumentParserTest extends TestCase
{
    public function testRemoveConsecutive(): void
    {
        $tags = app(DocumentParser::class)->removeConsecutiveNumbers(['Audible', 'Audible2', 'Audible3']);
        self::assertEquals(['Audible'], $tags);
    }

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
Product managers have to make many decisions every day, including product prioritization decisions, product design decisions, bug triage decisions, and many more. And the process by which a product manager makes such decisions can result either in an extremely well functioning team dynamic or... quite the opposite.

When it works well, the team feels as if the best ideas, regardless of where they came from, get implemented. They know their input will get heard. And they have a clear understanding of how and when decisions will be made. And even though the wrong decisions might sometimes be made, they know that when appropriate, they can and will be reversed. And while they may not always agree with the outcome of a decision, they trust in the team’s ability to make the right call more often than not. And they believe the team can take on any challenge that might be put before them and solve it effectively.

On the other hand, when the decision making process is not working well, the team starts questioning a lot of the decisions being made. The team wonders whether the decisions were hastily made without taking into account all the relevant information. The team doesn’t feel as if they understand how such decisions are made and may even believe the team suffers from hippo (highest paid person’s opinion) decision-making. And they ultimately don’t trust in the team’s ability to make the right call.

The surprising thing about the difference between the well functioning team and the alternative is not the actual decisions being made, but the process and culture within which such decisions are made. To build the right decision making culture, I encourage product managers to follow the following best practices.

Establish your role as the curator, not the creator of great ideas
Great products are built by great teams and as such the best ideas can come from anyone on the team. It’s important for your team to understand this and truly take it to heart. When your team believes that your role is to curate the best ideas and make sure they bubble up to the top, they’ll be eager to participate in the process.

Make people’s opinions feel heard
Once you establish your role, the most important thing to do is ensure people’s opinions feel heard. This means making yourself accessible to hearing ideas from the team and taking the time to carefully consider them. Be careful not to immediately shoot down ideas you don’t agree with. Instead try to understand their point of view and paraphrase their recommendation in your own words so they know that you’ve understood them.

Communicate the decision making process
When you are in the middle of making a critical decision, it’s important to make sure the team understands the decision making process. If you’re still in the phase of gathering potential recommendations, make that clear and solicit ideas from the team. If you waiting on additional information before making the call, it’s important the team understands that as well so they know why a decision is taking additional time. And when the decision is eventually made, make it clear to the team what the decision is, the reasoning behind it, and that it’s time to move on to the next challenge.

Favor decisions today over decisions tomorrow
The enemy of decision making is time. Decisions today can move the product forward today, versus decisions in the future simply slow down learning, cause people to wonder why it’s taking so long, and reduce speed of execution. Therefore it’s important to favor making the call today rather than later. Unless there is clear additional information you are waiting on before making a call, always favor just making the call right now.

Make the process for revisiting decisions clear
You’ll inevitably end up in situations where you’ve made the wrong call and have to revisit a decision. It’s important to allow yourself to do this, but at the same time establish a clear process for doing so. I like to always ask what new information is now known that is causing us to revisit the decision we made earlier? It’s a great bar for ensuring that decisions are not constantly being revisited and flip-flopped, yet ensuring there is a mechanism for changing course when appropriate.

With these five best practices, you are on your way to establishing the right decision making culture on your team to ensure an extremely well functioning team dynamic.
DOCUMENT_TEXT;

        $title = 'What does it mean to be an entrepreneur';

        $results = app(DocumentParser::class)->getTags($title, $documentText);
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
