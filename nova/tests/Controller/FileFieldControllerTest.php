<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Tests\Fixtures\File;
use Laravel\Nova\Tests\Fixtures\Role;
use Laravel\Nova\Tests\Fixtures\SoftDeletingFile;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserPolicy;
use Laravel\Nova\Tests\IntegrationTest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileFieldControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanStoreAFile()
    {
        Storage::fake();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response->assertStatus(201);
        Storage::disk()->assertExists('avatars/avatar.png');

        $file = File::first();
        $this->assertEquals('avatars/avatar.png', $file->avatar);
    }

    public function testUpdatePrunableFile()
    {
        $_SERVER['nova.fileResource.imageField'] = function () {
            return Image::make('Avatar', 'avatar')->prunable();
        };

        $this->withExceptionHandling()
            ->postJson('/nova-api/files', [
                'avatar' => UploadedFile::fake()->image('avatar.png'),
            ]);

        $_SERVER['__nova.fileResource.imageName'] = 'avatar2.png';

        $file = File::first();

        $filename = $file->avatar;
        Storage::disk('public')->assertExists($file->avatar);

        $this->withExceptionHandling()
            ->postJson('/nova-api/files/'.$file->id, [
                '_method'=> 'PUT',
                'avatar' => UploadedFile::fake()->image('avatar2.png'),
            ]);

        unset($_SERVER['nova.fileResource.imageField']);

        $file = File::first();

        Storage::disk('public')->assertMissing($filename);
        Storage::disk('public')->assertExists($file->avatar);
        $this->assertnotEquals($filename, $file->avatar);
    }

    public function testUpdatePrunableFileWithCustomDeleteCallback()
    {
        $_SERVER['nova.fileResource.imageField'] = function () {
            return Image::make('Avatar', 'avatar')
                ->prunable()
                ->delete(function ($request, $model, $disk, $path) {
                    Storage::disk($disk)->delete($path);
                });
        };

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/files', [
                'avatar' => UploadedFile::fake()->image('avatar.png'),
            ]);

        $response->assertStatus(201);

        $_SERVER['__nova.fileResource.imageName'] = 'avatar2.png';

        $file = File::first();

        $filename = $file->avatar;
        Storage::disk('public')->assertExists($file->avatar);

        $this->withExceptionHandling()
            ->postJson('/nova-api/files/'.$file->id, [
                '_method'=> 'PUT',
                'avatar' => UploadedFile::fake()->image('avatar2.png'),
            ]);

        unset($_SERVER['nova.fileResource.imageField']);

        $file = File::first();

        Storage::disk('public')->assertMissing($filename);
        Storage::disk('public')->assertExists($file->avatar);
        $this->assertnotEquals($filename, $file->avatar);
    }

    public function testProperResponseReturnedWhenRequiredFileNotProvided()
    {
        Storage::fake();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => null,
                        ]);

        $response->assertStatus(422);
        Storage::disk()->assertMissing('avatars/avatar.png');
    }

    public function testFileFieldReturnsProperMetaData()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->getJson('/nova-api/files/'.File::first()->id);

        $response->assertStatus(200);
        $file = $response->original['resource']['fields'][1]->jsonSerialize();
        $this->assertTrue($file['downloadable']);
        $this->assertEquals('/storage/avatars/avatar.png', $file['thumbnailUrl']);
    }

    public function testFileCanBeDownloaded()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/files/'.File::first()->id.'/download/avatar');

        $response->assertStatus(200);
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    public function testFileFieldCanBeDeleted()
    {
        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/files/'.File::first()->id.'/field/avatar');

        $response->assertStatus(200);
        $this->assertCount(2, File::first()->actions);
    }

    public function testPivotFileFieldCanBeDeleted()
    {
        Storage::fake('public');

        $_SERVER['__nova.user.pivotFile'] = true;
        $_SERVER['__nova.role.pivotFile'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/attach/roles', [
                            'roles'           => $role->id,
                            'admin'           => 'Y',
                            'photo'           => $image = UploadedFile::fake()->image('avatar.png'),
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(200);

        Storage::disk('public')->assertExists($image->hashName());

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users/'.$user->id.'/roles/'.$role->id.'/field/photo?viaRelationship=roles');

        $response->assertStatus(200);
        Storage::disk('public')->assertMissing($image->hashName());

        unset($_SERVER['__nova.user.pivotFile']);
        unset($_SERVER['__nova.role.pivotFile']);
    }

    public function testPivotFileFieldCantBeDeletedIfNotAuthorizedToAttachTheRelatedResource()
    {
        Storage::fake('public');

        $_SERVER['__nova.user.pivotFile'] = true;
        $_SERVER['__nova.role.pivotFile'] = true;

        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/users/'.$user->id.'/attach/roles', [
                            'roles'           => $role->id,
                            'admin'           => 'Y',
                            'photo'           => $image = UploadedFile::fake()->image('avatar.png'),
                            'viaRelationship' => 'roles',
                        ]);

        $response->assertStatus(200);

        Storage::disk('public')->assertExists($image->hashName());

        $_SERVER['nova.user.authorizable'] = true;
        $_SERVER['nova.user.attachRole'] = false;
        Gate::policy(User::class, UserPolicy::class);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/users/'.$user->id.'/roles/'.$role->id.'/field/photo?viaRelationship=roles');

        unset($_SERVER['nova.user.authorizable']);
        unset($_SERVER['nova.user.attachRole']);

        $response->assertStatus(403);
        Storage::disk('public')->assertExists($image->hashName());

        unset($_SERVER['nova.user.attachRole']);
        unset($_SERVER['__nova.user.pivotFile']);
        unset($_SERVER['__nova.role.pivotFile']);
    }

    public function testValueCanBeReturnedFromDeleteCallbackAndSetsColumnsValue()
    {
        $_SERVER['__nova.fileDelete'] = 'some-value';

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/files/'.File::first()->id.'/field/avatar');

        $response->assertStatus(200);
        $this->assertEquals('some-value', File::first()->avatar);
    }

    public function testArrayValueCanBeReturnedFromDeleteCallbackAndSetsColumnsValues()
    {
        $_SERVER['__nova.fileDelete'] = ['avatar' => 'test-avatar', 'name' => 'test-name'];

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/files/'.File::first()->id.'/field/avatar');

        $response->assertStatus(200);
        $this->assertEquals('test-avatar', File::first()->avatar);
        $this->assertEquals('test-name', File::first()->name);
    }

    public function testExtraFileInformationCanBeStoredUsingHelpers()
    {
        $_SERVER['nova.fileResource.imageField'] = function ($request) {
            return Image::make('Avatar')
                    ->disk('local')
                    ->path('avatars')
                    ->storeAs(function ($request) {
                        return 'avatar.png';
                    })
                    ->storeOriginalName('original_name')
                    ->storeSize('size')
                    ->prunable();
        };

        Storage::fake();

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        unset($_SERVER['nova.fileResource.imageField']);

        $response->assertStatus(201);
        Storage::disk()->assertExists('avatars/avatar.png');

        $file = File::first();
        $this->assertEquals('avatars/avatar.png', $file->avatar);
        $this->assertEquals('avatar.png', $file->original_name);
        $this->assertGreaterThan(0, $file->size);
    }

    public function testFileFieldsAreDeletedWhenResourceIsDeleted()
    {
        unset($_SERVER['__nova.fileDeleted']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/files', [
                            'resources' => [File::first()->id],
                        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, File::count());
        $this->assertTrue($_SERVER['__nova.fileDeleted']);

        unset($_SERVER['__nova.fileDeleted']);
    }

    public function testSoftDeletingFileFieldsAreNotDeletedWhenResourceIsSoftDeleted()
    {
        unset($_SERVER['__nova.fileDeleted']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/soft-deleting-files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/soft-deleting-files', [
                            'resources' => [SoftDeletingFile::first()->id],
                        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, SoftDeletingFile::count());
        $this->assertEquals(1, SoftDeletingFile::withTrashed()->count());
        $this->assertFalse(array_key_exists('__nova.fileDeleted', $_SERVER));

        unset($_SERVER['__nova.fileDeleted']);
    }

    public function testSoftDeletingFileFieldsAreDeletedWhenResourceIsForceDeleted()
    {
        unset($_SERVER['__nova.fileDeleted']);

        $response = $this->withExceptionHandling()
                        ->postJson('/nova-api/soft-deleting-files', [
                            'avatar' => UploadedFile::fake()->image('avatar.png'),
                        ]);

        $response = $this->withExceptionHandling()
                        ->deleteJson('/nova-api/soft-deleting-files/force', [
                            'resources' => [SoftDeletingFile::first()->id],
                        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, SoftDeletingFile::withTrashed()->count());
        $this->assertTrue($_SERVER['__nova.fileDeleted']);

        unset($_SERVER['__nova.fileDeleted']);
    }

    public function testPropertyNameCollision()
    {
        Storage::fake();

        $_SERVER['nova.fileResource.imageField'] = function ($request) {
            return Image::make('Files', 'files', 'public')
                ->path('avatars');
        };

        $response = $this->withExceptionHandling()
            ->postJson('/nova-api/files', [
                'files' => UploadedFile::fake()->image('avatar.png'),
            ]);

        unset($_SERVER['nova.fileResource.imageField']);

        $response->assertStatus(201);
    }

    public function testCallableResultOnStoreCallback()
    {
        Storage::fake();

        $_SERVER['nova.fileResource.imageField'] = function ($request) {
            return Image::make('Avatar', 'avatar', 'public')
                        ->store(function (Request $request, $model) {
                            return function () use ($request, $model) {
                                $model->avatar = $request->file('avatar')->store('avatars', 'public');
                            };
                        });
        };

        $response = $this->withExceptionHandling()
             ->postJson('/nova-api/files', [
                 'avatar' => UploadedFile::fake()->image('avatar.png'),
             ]);

        unset($_SERVER['nova.fileResource.imageField']);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->original['resource']['avatar']);
        $this->assertEmpty(File::query()->first()->avatar);
    }
}
