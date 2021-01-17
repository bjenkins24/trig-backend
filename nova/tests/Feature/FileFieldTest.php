<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tests\Fixtures\VaporFile as Model;
use Laravel\Nova\Tests\Fixtures\VaporFileResource;
use Laravel\Nova\Tests\IntegrationTest;

class FileFieldTest extends IntegrationTest
{
    protected function makeField($name = 'Avatar', $attribute = 'avatar')
    {
        return File::make($name, $attribute);
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
                return new FakeDownloadResponse(sprintf('http://mycdn.com/downloads/%s', $model->avatar));
            });

            tap(
                $field->toDownloadResponse(NovaRequest::create('/', 'GET'), new VaporFileResource($resource)),
                function ($instance) {
                    $this->assertInstanceOf(FakeDownloadResponse::class, $instance);
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
        Storage::fake();
        Storage::fake('public');

        $model = new Model();
        $field = $this->makeField();
        $field->storeAs(function () {
            return 'david.jpg';
        });

        $request = NovaRequest::create('/', 'GET', [], [], [
            'avatar' => UploadedFile::fake()->image('wew.jpg'),
        ]);

        $field->fill($request, $model);

        $this->assertEquals('david.jpg', $model->avatar);

        Storage::disk('public')->assertExists('david.jpg');
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

class FakeDownloadResponse
{
    public $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
}
