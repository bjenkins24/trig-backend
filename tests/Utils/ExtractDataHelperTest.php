<?php

namespace Tests\Utils;

use App\Utils\ExtractDataHelper;
use App\Utils\TikaWebClientWrapper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtractDataHelperTest extends TestCase
{
    use RefreshDatabase;

    private function mockGetData($withTitle = true): array
    {
        $content = '<div><img alt="statue" height="240" src="https://sachin-rekhi.s3-us-west-1.amazonaws.com/blog/decision-making.jpg" width="179"><p>Product managers have to make many decisions every day, including product prioritization decisions, product design decisions, bug triage decisions, and many more. And the process by which a product manager makes such decisions can result either in an extremely well functioning team dynamic or quite the opposite. Product managers</p></div>';

        $correctResult = [
            'title'                        => $withTitle ? 'My fake title' : 'Product managers have to make many decisions every day',
            'keyword'                      => 'my cool keyword',
            'author'                       => 'Brian Jenkins',
            'last_author'                  => 'Joe Rodriguez',
            'encoding'                     => 'utf_8',
            'comment'                      => 'Hello friends',
            'language'                     => 'en_US',
            'subject'                      => 'subject stuff',
            'revisions'                    => 'cool revisions',
            'created'                      => '2020-10-14',
            'modified'                     => '2020-10-16',
            'print_date'                   => '2020-10-27',
            'save_date'                    => '2020-10-28',
            'line_count'                   => 25,
            'page_count'                   => 27,
            'paragraph_count'              => 25,
            'word_count'                   => 200,
            'character_count'              => 400,
            'character_count_with_spaces'  => 600,
            'width'                        => 200,
            'height'                       => 400,
            'copyright'                    => 'My cool copyright',
            'excerpt'                      => 'Product managers have to make many decisions every day, including product prioritization decisions, product design decisions, bug triage decisions, and many more. And the process by which a product',
            'content'                      => '<p>Product managers have to make many decisions every day, including product prioritization decisions, product design decisions, bug triage decisions, and many more. And the process by which a product manager makes such decisions can result either in an extremely well functioning team dynamic or quite the opposite. Product managers</p>',
        ];

        $fakeMeta = [
            'meta' => [
                'dc:title'                         => $withTitle ? $correctResult['title'] : '',
                'meta:keyword'                     => $correctResult['keyword'],
                'meta:author'                      => $correctResult['author'],
                'meta:last-author'                 => $correctResult['last_author'],
                'encoding'                         => $correctResult['encoding'],
                'comment'                          => $correctResult['comment'],
                'language'                         => $correctResult['language'],
                'cp:subject'                       => $correctResult['subject'],
                'cp:revision'                      => $correctResult['revisions'],
                'meta:creation-date'               => $correctResult['created'],
                'Last-Modified'                    => $correctResult['modified'],
                'meta:print-date'                  => $correctResult['print_date'],
                'meta:save-date'                   => $correctResult['save_date'],
                'meta:line-count'                  => $correctResult['line_count'],
                'meta:page-count'                  => $correctResult['page_count'],
                'meta:paragraph-count'             => $correctResult['paragraph_count'],
                'meta:word-count'                  => $correctResult['word_count'],
                'meta:character-count'             => $correctResult['character_count'],
                'meta:character-count-with-spaces' => $correctResult['character_count_with_spaces'],
                'width'                            => $correctResult['width'],
                'height'                           => $correctResult['height'],
                'Copyright'                        => $correctResult['copyright'],
            ],
            'width'     => $correctResult['width'],
            'height'    => $correctResult['height'],
            'Copyright' => $correctResult['copyright'],
        ];

        $this->mock(TikaWebClientWrapper::class, static function ($mock) use ($fakeMeta, $content) {
            $mock->shouldReceive('getMetaData')->once()->andReturn($fakeMeta);
            $mock->shouldReceive('getHtml')->once()->andReturn($content);
        });

        return $correctResult;
    }

    /**
     * @group n
     */
    public function testCleanOCR(): void
    {
        $output = <<<HTML
On Deck Founder Questionnaire: Self-Reflection yx eo a ®

File Edit View Insert Format Tools Add-ons Help Last edit was 11 days ago









i 1 ep A, FF 100% ~ Heading 1 ~ Arial ~ - 2@ + BIUA #&amp; @o@HPRry SSEBE TE EV EYEE X @Z Editing yoa
1 = 1 2 3 4 5 6 v 7

On Deck “

On Deck ‘

Founder Questionnaire: Self-Reflection

As you begin the early stages of idea exploration or seeking out co-founders, we suggest you
invest time in better understanding yourself and your goals.

This self-reflection exercise will inform which ideas you may choose to pursue (i.e.those you
have “Founder Market Fit” with), and the other roles you'll need to add to your founding team.

Objectives of this exercise:

e Increase self-awareness
o Learn about yourself: what are your motivations, fears, unique edges.
e Define your goals
o What kind of business do you want to build? What lifestyle, financial, and risk
tradeoffs do you want to optimize for?
e Align on values
o Determine whether you agree on first principles with prospective co-founders
e Identify your critical risks
o What gaps do you have on your founding team that are existential to your
success?
e Be vulnerable and honest with each other
o Understanding motivations, fears, and working styles is critical to working
together effectively and building trust quickly. This guide can serve as a tool for
easing into those conversations.
e Highlight what roles you need on your founding team
o Developing an understanding of your collective weak spots can be useful for both
making those early hires to add to your team and for identifying your own
personal areas of growth.

On Deck

Ikigai: the pursuit of purpose

There’s a Japanese concept called Jkigai. It refers to finding a direction or purpose in life, that
which makes one's life worthwhile, and towards which an individual takes spontaneous and
willing actions giving them satisfaction and a sense of meaning to life. Ikigai comes at the
intersection of four things: what you’re good at, what can make you money, what the world
needs, and what you’re passionate about. For example, your passion lies at the intersection of
what you’re good at, and what you love.

It's possible to spreadsheet this out, list all the things you’ve ever thought about doing and score

them on these four axes. For example, if there’s two things that you’re neutral on, but one of
them pays more than the other, or it’s something the world needs, you may choose that one.

Try listing these below, and ranking them FINDING YOUR IKIGAI

1. What do you love?

a. Product WHAT | LOVE
b. Marketing
c. Analytics
d. Technology
e. Programming wearin z WORLD NEEDS
f. Music
2. What does the world need? WHAT | CAN

BE PAID FOR

3. What can you be paid for?

4. What are you good at?
HTML;

        $expectedCleaned = <<<HTML
On Deck “

On Deck ‘

Founder Questionnaire: Self-Reflection

As you begin the early stages of idea exploration or seeking out co-founders, we suggest you invest time in better understanding yourself and your goals.

This self-reflection exercise will inform which ideas you may choose to pursue (i.e.those you have “Founder Market Fit” with), and the other roles you'll need to add to your founding team.

Objectives of this exercise:

<ul><li>Increase self-awareness<li>Learn about yourself: what are your motivations, fears, unique edges.</li></li><li>Define your goals o What kind of business do you want to build? What lifestyle, financial, and risk tradeoffs do you want to optimize for?</li><li>Align on values<li>Determine whether you agree on first principles with prospective co-founders</li></li><li>Identify your critical risks<li>What gaps do you have on your founding team that are existential to your success?</li></li><li>Be vulnerable and honest with each other<li>Understanding motivations, fears, and working styles is critical to working together effectively and building trust quickly. This guide can serve as a tool for easing into those conversations.</li></li><li>Highlight what roles you need on your founding team<li>Developing an understanding of your collective weak spots can be useful for both making those early hires to add to your team and for identifying your own personal areas of growth.</li></li></ul>

On Deck

Ikigai: the pursuit of purpose

There’s a Japanese concept called Jkigai. It refers to finding a direction or purpose in life, that which makes one's life worthwhile, and towards which an individual takes spontaneous and willing actions giving them satisfaction and a sense of meaning to life. Ikigai comes at the intersection of four things: what you’re good at, what can make you money, what the world needs, and what you’re passionate about. For example, your passion lies at the intersection of what you’re good at, and what you love.

It's possible to spreadsheet this out, list all the things you’ve ever thought about doing and score

them on these four axes. For example, if there’s two things that you’re neutral on, but one of them pays more than the other, or it’s something the world needs, you may choose that one.

Try listing these below, and ranking them FINDING YOUR IKIGAI

1. What do you love?

a. Product WHAT | LOVE b. Marketing c. Analytics d. Technology e. Programming wearin z WORLD NEEDS f. Music 2. What does the world need? WHAT | CAN

BE PAID FOR

3. What can you be paid for?

4. What are you good at?
HTML;
        $filename = 'sup.png';

        $result = app(ExtractDataHelper::class)->cleanOCR($filename, $output);
        self::assertEquals($expectedCleaned, $result);
    }

    public function testGetFileData(): void
    {
        Storage::fake();
        $correctResult = $this->mockGetData();
        // Title case
        $correctResult['title'] = 'My Fake Title';
        $result = app(ExtractDataHelper::class)->getFileData('application/pdf', 'my name is brian');
        self::assertEquals($correctResult, $result->toArray());
    }

    public function testGetFileDataNoTitle(): void
    {
        Storage::fake();
        $correctResult = $this->mockGetData(false);
        $result = app(ExtractDataHelper::class)->getFileData('application/pdf', 'my name is brian');
        self::assertEquals($correctResult, $result->toArray());
    }

    public function testGetFileDataNoExtension(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $result = app(ExtractDataHelper::class)->getFileData('fake-mime', 'my name is brian');
        self::assertEquals([], $result->toArray());
    }

    /**
     * @dataProvider excludedExtensionProvider
     */
    public function testGetFileDataExcludedExtension(string $mimeType): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $result = app(ExtractDataHelper::class)->getFileData($mimeType, 'my name is brian');
        self::assertEquals([], $result->toArray());
    }

    public function excludedExtensionProvider(): array
    {
        return [
            ['video/quicktime'],
            ['audio/mpeg'],
            ['application/zip'],
        ];
    }

    public function testFailedGetFileData(): void
    {
        $this->mock(TikaWebClientWrapper::class);
        $this->mock(TikaWebClientWrapper::class, static function ($mock) {
            $mock->shouldReceive('getMetaData')->andThrow(new Exception('Yes!'))->once();
        });
        $result = app(ExtractDataHelper::class)->getFileData('application/pdf', 'my name is brian');
        self::assertEquals([], $result->toArray());
    }

    public function getMockDataResult($content)
    {
        $data = new FakeMetaData();
        $meta = $data->meta;

        return collect([
            'title'                       => $meta->{'dc:title'},
            'keyword'                     => $meta->{'meta:keyword'},
            'author'                      => $meta->{'meta:author'},
            'last_author'                 => $meta->{'meta:last-author'},
            'encoding'                    => $meta->{'encoding'},
            'comment'                     => $meta->{'comment'},
            'language'                    => $meta->{'language'},
            'subject'                     => $meta->{'cp:subject'},
            'revisions'                   => $meta->{'cp:revision'},
            'created'                     => $meta->{'meta:creation-date'},
            'modified'                    => $meta->{'Last-Modified'},
            'print_date'                  => $meta->{'meta:print-date'},
            'save_date'                   => $meta->{'meta:save-date'},
            'line_count'                  => $meta->{'meta:line-count'},
            'page_count'                  => $meta->{'meta:page-count'},
            'paragraph_count'             => $meta->{'meta:paragraph-count'},
            'word_count'                  => $meta->{'meta:word-count'},
            'character_count'             => $meta->{'meta:character-count'},
            'character_count_with_spaces' => $meta->{'meta:character-count-with-spaces'},
            'width'                       => $data->width,
            'height'                      => $data->height,
            'copyright'                   => $data->Copyright,
            'content'                     => $content,
        ]);
    }
}

class FakeMeta
{
    public $encoding = 'cool encoding';

    public function __construct()
    {
        $this->{'dc:title'} = 'title';
        $this->{'meta:keyword'} = 'keyword';
        $this->{'meta:author'} = 'author';
        $this->{'meta:last-author'} = 'last-author';
        $this->{'encoding'} = 'encoding';
        $this->{'comment'} = 'comment';
        $this->{'language'} = 'language';
        $this->{'cp:subject'} = 'subject';
        $this->{'cp:revision'} = 'revision';
        $this->{'meta:creation-date'} = '2020-05-22T21:05:36+08:00';
        $this->{'Last-Modified'} = '2020-05-22T21:05:36+00:00';
        $this->{'meta:print-date'} = '2020-05-22T12:05:36+00:00';
        $this->{'meta:save-date'} = '2020-05-22T9:05:36+00:00';
        $this->{'meta:line-count'} = 'line-count';
        $this->{'meta:page-count'} = 'page-count';
        $this->{'meta:paragraph-count'} = 'paragraph-count';
        $this->{'meta:word-count'} = 'word-count';
        $this->{'meta:character-count'} = 'character-count';
        $this->{'meta:character-count-with-spaces'} = 'character-count-with-spaces';
    }
}

class FakeMetaData
{
    public $width = 'width';
    public $height = 'height';
    public $Copyright = 'copyright';
    public $comment = 'comment';

    public function __construct()
    {
        $this->meta = new FakeMeta();
    }
}
