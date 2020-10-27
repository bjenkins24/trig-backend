<?php

namespace Tests\Utils;

use App\Utils\ExtractDataHelper;
use App\Utils\TikaWebClient\TikaWebClientInterface;
use Exception;
use Tests\TestCase;

class ExtractDataHelperTest extends TestCase
{
    public function testGetFileData(): void
    {
        \Storage::fake();

        $myData = [
            'my first data'  => 'my value',
            'my second data' => 'my cool value',
        ];
        $this->partialMock(ExtractDataHelper::class, static function ($mock) use ($myData) {
            $mock->shouldReceive('getData')->andReturn($myData)->once();
        });
        $result = app(ExtractDataHelper::class)->getFileData('application/pdf', 'my name is brian');
        self::assertEquals($result->toArray(), $myData);
    }

    public function testGetFileDataNoExtension(): void
    {
        $this->partialMock(ExtractDataHelper::class, static function ($mock) {
            $mock->shouldReceive('getData')->andReturn(['cool stuff', 'goes here']);
        });
        $result = app(ExtractDataHelper::class)->getFileData('fake-mime', 'my name is brian');
        self::assertEquals([], $result->toArray());
    }

    /**
     * @dataProvider excludedExtensionProvider
     */
    public function testGetFileDataExcludedExtension(string $mimeType): void
    {
        $this->partialMock(ExtractDataHelper::class, static function ($mock) {
            $mock->shouldReceive('getData')->andReturn(['cool stuff', 'goes here']);
        });
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
        \Storage::fake();

        $this->partialMock(ExtractDataHelper::class, static function ($mock) {
            $mock->shouldReceive('getData')->andThrow(new Exception('Yes!'))->once();
        });
        $result = app(ExtractDataHelper::class)->getFileData('application/pdf', 'my name is brian');
        self::assertEquals($result->toArray(), []);
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

    /**
     * @throws Exception
     */
    public function testGetData(): void
    {
        $content = 'my cool content';
        $data = new FakeMetaData();
        $mock = \Mockery::mock(TikaWebClientInterface::class);
        $mock->shouldReceive('getText')->andReturn($content)->once()->mock();
        $mock->shouldReceive('getMetadata')->andReturn($data)->once()->mock();
        $extractDataHelper = new ExtractDataHelper($mock);
        $result = $extractDataHelper->getData('my file');
        $meta = $data->meta;

        self::assertEquals($this->getMockDataResult($content)->toArray(), $result);
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
