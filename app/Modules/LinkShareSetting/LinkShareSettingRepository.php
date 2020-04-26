<?php

namespace App\Modules\LinkShareSetting;

use App\Models\Card;
use App\Models\LinkShareSetting;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use App\Modules\Permission\PermissionRepository;

class LinkShareSettingRepository
{
    private LinkShareSetting $linkShareSetting;
    private LinkShareTypeRepository $linkShareType;
    private PermissionRepository $permission;
    private CapabilityRepository $capability;

    public function __construct(
        LinkShareSetting $linkShareSetting,
        LinkShareTypeRepository $linkShareType,
        PermissionRepository $permission,
        CapabilityRepository $capability
    ) {
        $this->linkShareSetting = $linkShareSetting;
        $this->linkShareType = $linkShareType;
        $this->permission = $permission;
        $this->capability = $capability;
    }

    public function createIfNew($type, string $shareType, string $capability)
    {
        $path = null;
        if ($type instanceof Card) {
            $path = Card::class;
        }
        $linkShareType = $this->linkShareType->get($shareType);
        $capabilityId = $this->capability->get($capability)->id;
        $settingExists = $this->linkShareSetting->where([
            'link_share_type_id' => $linkShareType->id,
            'capability_id'      => $capabilityId,
            'shareable_type'     => $path,
            'shareable_id'       => $type->id,
        ])->exists();
        if ($settingExists) {
            return false;
        }

        return $type->linkShareSetting()->create([
            'link_share_type_id' => $linkShareType->id,
            'capability_id'      => $capabilityId,
        ]);
    }

    public function createAnyoneOrganizationIfNew($type, string $capability): LinkShareSetting
    {
        $this->createIfNew($type, LinkShareTypeRepository::ANYONE_ORGANIZATION, $capability);
    }

    public function createAnyoneIfNew($type, string $capability): LinkShareSetting
    {
        $this->createIfNew($type, LinkShareTypeRepository::ANYONE, $capability);
    }

    public function createPublicIfNew($type, string $capability): LinkShareSetting
    {
        // If it's public it should be discoverable by anyone as well
        $this->permission->createAnyone($type, $capability);

        return $this->createIfNew($type, LinkShareTypeRepository::PUBLIC_SHARE, $capability);
    }
}
