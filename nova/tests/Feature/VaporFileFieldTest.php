<?php

namespace Laravel\Nova\Tests\Feature;

use Faker\Provider\Uuid;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\VaporFile;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tests\Fixtures\VaporFile as Model;
use Laravel\Nova\Tests\Fixtures\VaporFileResource;
use Laravel\Nova\Tests\IntegrationTest;

class VaporFileFieldTest extends IntegrationTest
{
    protected function makeField($name = 'Avatar', $attribute = 'avatar')
    {
        return VaporFile::make($name, $attribute);
    }

    protected function createModel()
    {
        return Model::create([
            'avatar' => 'wew.jpg',
        ]);
    }

    protected function assertFixture($callback)
    {
        $model = $this->createModel();

        $field = $this->makeField()
            ->thumbnail(function ($value, $disk, $resource) {
                return sprintf('http://mycdn.com/%s', $resource->avatar);
            })
            ->preview(function ($att, $disk, $resource) {
                return sprintf('http://mycdn.com/previews/%s', $resource->avatar);
            })
            ->delete(function () {
                return 'deleted!';
            })
            ->acceptedTypes('image/*')
            ->prunable();

        $field->resolve($model);

        call_user_func($callback, $field, $model);
    }

    public function testFieldCanAcceptAThumbailCallback()
    {
        $this->assertFixture(function ($field) {
            $this->assertEquals('http://mycdn.com/wew.jpg', $field->jsonSerialize()['thumbnailUrl']);
        });
    }

    public function testFieldCanAcceptAPreviewCallback()
    {
        $this->assertFixture(function ($field) {
            $this->assertEquals('http://mycdn.com/previews/wew.jpg', $field->jsonSerialize()['previewUrl']);
        });
    }

    public function testTheresNoThumbnailByDefault()
    {
        tap($this->makeField(), function ($field) {
            $this->assertNull($field->jsonSerialize()['thumbnailUrl']);
        });
    }

    public function testTheresNoPreviewByDefault()
    {
        tap($this->makeField(), function ($field) {
            $this->assertNull($field->jsonSerialize()['previewUrl']);
        });
    }

    public function testItIsDownloadableByDefault()
    {
        tap($this->makeField(), function ($field) {
            $resource = $this->createModel();

            $field->resolve($resource);

            $this->assertTrue($field->jsonSerialize()['downloadable']);
        });
    }

    public function testDownloadsCanBeDisabled()
    {
        $this->assertFixture(function ($field, $resource) {
            $field->disableDownload();
            $this->assertFalse($field->jsonSerialize()['downloadable']);
        });
    }

    public function testDownloadResponseCanBeSet()
    {
        $this->assertFixture(function ($field, $resource) {
            $field->download(function ($request, $model) {
                return new VaporFakeDownloadResponse(sprintf('http://mycdn.com/downloads/%s', $model->avatar));
            });

            tap(
                $field->toDownloadResponse(NovaRequest::create('/', 'GET'), new VaporFileResource($resource)),
                function ($instance) {
                    $this->assertInstanceOf(VaporFakeDownloadResponse::class, $instance);
                    $this->assertEquals('http://mycdn.com/downloads/wew.jpg', $instance->path);
                }
            );
        });
    }

    public function testIsDeletableByDefault()
    {
        tap($this->makeField(), function ($field) {
            $this->assertTrue($field->jsonSerialize()['deletable']);
        });
    }

    public function testDeleteStrategyCanBeCustomized()
    {
        $this->assertFixture(function ($field) {
            $field->deleteCallback == function () {
                return 'deleted!';
            };
        });
    }

    public function testCanSetTheAcceptedFileTypes()
    {
        $this->assertFixture(function ($field) {
            $this->assertEquals('image/*', $field->acceptedTypes);
        });
    }

    public function testCanCorrectlyFillTheMainAttributeAndStoreFile()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key' => 'tmp/'.$uuid,
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals($uuid, $model->avatar);

        Storage::assertExists($uuid);
    }

    public function testCanCustomizeFileNameStrategy()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();
        $field->storeAs(function () {
            return 'bar';
        });

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key' => 'tmp/'.$uuid,
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals('bar', $model->avatar);

        Storage::assertExists('bar');
    }

    public function testCanCustomizeFilePathStrategy()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();
        $field->path('foo');

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key' => 'tmp/'.$uuid,
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals('foo/'.$uuid, $model->avatar);
        Storage::assertExists('foo/'.$uuid);
    }

    public function testCanStoreFileExtension()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();
        $field->storeAs(function ($request) {
            return $request->input('vaporFile')['avatar']['key'].'.'.$request->input('vaporFile')['avatar']['extension'];
        });

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key'       => 'tmp/'.$uuid,
                    'extension' => 'jpg',
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals('tmp/'.$uuid.'.jpg', $model->avatar);
        Storage::assertExists('tmp/'.$uuid.'.jpg');
    }

    public function testCanStoreOriginalFilename()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();
        $field->storeAs(function ($request) {
            return $request->input('vaporFile')['avatar']['filename'];
        });

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key'       => 'tmp/'.$uuid,
                    'filename'  => 'wow.png',
                    'extension' => 'jpg',
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals('wow.png', $model->avatar);
        Storage::assertExists('wow.png');
    }

    public function testCanCustomizeFilePathAndNameStrategy()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
//        $file->path('foo');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();
        $field->path('foo');
        $field->storeAs(function () {
            return 'wew';
        });

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key' => 'tmp/'.$uuid,
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals('foo/wew', $model->avatar);
        Storage::assertExists('foo/wew');
    }

    public function testCanCorrectlyStoreExtraColumns()
    {
        config(['filesystems.default' => 's3']);
        config()->offsetUnset('filesystems.disks.local');
        config()->offsetUnset('filesystems.disks.public');

        Storage::fake('s3');
        $uuid = Uuid::uuid();
        $file = UploadedFile::fake()->image('wew.jpg');
        $file->storeAs('tmp', $uuid, 's3');
        Storage::disk('s3')->assertExists('tmp/'.$uuid);

        $model = new Model();
        $field = $this->makeField();
        $field->storeOriginalName('original_name');

        $request = NovaRequest::create('/', 'GET', [
            'avatar'    => 'wew.jpg',
            'vaporFile' => [
                'avatar' => [
                    'key' => 'tmp/'.$uuid,
                ],
            ],
        ]);

        $field->fill($request, $model);

        $this->assertEquals($uuid, $model->avatar);
        $this->assertEquals('wew.jpg', $model->original_name);

        Storage::assertExists($uuid);
    }

    public function testFieldIsPrunable()
    {
        $this->assertFixture(function ($field) {
            $this->assertTrue($field->isPrunable());
            $field->prunable(false);
            $this->assertFalse($field->isPrunable());
        });
    }
}

class VaporFakeDownloadResponse
{
    public $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
}
