<?php

namespace Tests\Feature\Modules\LinkShareSetting;

use App\Models\Card;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\LinkShareSetting\Exceptions\LinkShareSettingTypeNotSupported;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkShareSettingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->card = Card::first();
        $this->linkShareSettingRepo = app(LinkShareSettingRepository::class);
        $this->linkShareTypeRepo = app(LinkShareTypeRepository::class);
        $this->capability = 'reader';
        $this->capabilityId = app(CapabilityRepository::class)->get($this->capability)->id;
    }

    /**
     * Test creating an anyone workspace link share setting.
     *
     * @return void
     */
    public function testCreateAnyone()
    {
        $shareType = $this->linkShareTypeRepo::ANYONE;
        $this->linkShareSettingRepo->createAnyoneIfNew($this->card, $this->capability);
        $linkShareType = $this->linkShareTypeRepo->get($shareType);

        $this->assertDatabaseHas('link_share_settings', [
            'link_share_type_id' => $linkShareType->id,
            'capability_id'      => $this->capabilityId,
            'shareable_type'     => 'App\\Models\\Card',
            'shareable_id'       => 1,
        ]);

        // Now that it exists let's make sure it's not created again
        $result = $this->linkShareSettingRepo->createAnyoneIfNew($this->card, $this->capability);
        $this->assertFalse($result);
    }

    /**
     * Test creating an anyone workspace link share setting.
     *
     * @return void
     */
    public function testCreatePublic()
    {
        $shareType = $this->linkShareTypeRepo::PUBLIC_SHARE;
        $this->linkShareSettingRepo->createPublicIfNew($this->card, $this->capability);
        $linkShareType = $this->linkShareTypeRepo->get($shareType);

        $this->assertDatabaseHas('link_share_settings', [
            'link_share_type_id' => $linkShareType->id,
            'capability_id'      => $this->capabilityId,
            'shareable_type'     => 'App\\Models\\Card',
            'shareable_id'       => 1,
        ]);

        $this->assertDatabaseHas('permissions', [
            'permissionable_type' => 'App\Models\Card',
            'permissionable_id'   => 1,
            'capability_id'       => $this->capabilityId,
        ]);

        $this->assertDatabaseHas('permission_types', [
            'typeable_type' => null,
            'typeable_id'   => null,
        ]);

        // Now that it exists let's make sure it's not created again
        $result = $this->linkShareSettingRepo->createPublicIfNew($this->card, $this->capability);
        $this->assertFalse($result);
    }

    /**
     * Test that an error is thrown if we throw in a false type.
     *
     * @return void
     */
    public function testFalseType()
    {
        $this->expectException(LinkShareSettingTypeNotSupported::class);
        $this->linkShareSettingRepo->createAnyoneIfNew('test', $this->capability);
    }
}
