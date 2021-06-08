<?php

namespace Tests\Feature\Modules\Collection;

use App\Models\Collection;
use App\Models\User;
use App\Modules\Collection\CollectionRepository;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function testIsViewablePublic(): void
    {
        $collection = Collection::find(1);
        app(LinkShareSettingRepository::class)->createPublicIfNew($collection, 'reader');
        $result = app(CollectionRepository::class)->isViewable($collection);
        self::assertEquals(true, $result);
    }

    public function testIsViewablePrivate(): void
    {
        $collection = Collection::find(1);
        $result = app(CollectionRepository::class)->isViewable($collection);
        self::assertEquals(false, $result);
    }

    public function testIsViewableUser(): void
    {
        $collection = Collection::find(1);
        $result = app(CollectionRepository::class)->isViewable($collection, User::find(2));
        self::assertEquals(true, $result);
    }
}
