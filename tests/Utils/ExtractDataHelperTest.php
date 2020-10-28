<?php

namespace Tests\Utils;

use App\Utils\ExtractDataHelper;
use App\Utils\TikaWebClientWrapper;
use Exception;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtractDataHelperTest extends TestCase
{
    private function mockGetData(): array
    {
        $correctResult = [
            'title'                        => 'My fake title',
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
            'content'                      => 'Hello this is my content',
        ];

        $this->mock(TikaWebClientWrapper::class, static function ($mock) use ($correctResult) {
            $mock->shouldReceive('getMetaData')->once()->andReturn([
                'meta' => [
                    'dc:title'                         => $correctResult['title'],
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
            ]);
            $mock->shouldReceive('getText')->once()->andReturn($correctResult['content']);
        });

        return $correctResult;
    }

    public function testGetFileData(): void
    {
        Storage::fake();
        $correctResult = $this->mockGetData();
        $result = app(ExtractDataHelper::class)->getFileData('application/pdf', 'my name is brian');
        self::assertEquals($correctResult, $result->toArray());
    }

    public function testGetFileDataNoExtension(): void
    {
        Storage::fake();
        $this->mockGetData();
        $result = app(ExtractDataHelper::class)->getFileData('fake-mime', 'my name is brian');
        self::assertEquals([], $result->toArray());
    }

    /**
     * @dataProvider excludedExtensionProvider
     */
    public function testGetFileDataExcludedExtension(string $mimeType): void
    {
        Storage::fake();
        $this->mockGetData();
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
        Storage::fake();
        $this->mockGetData();
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
