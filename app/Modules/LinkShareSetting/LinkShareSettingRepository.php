<?php

namespace App\Modules\LinkShareSetting;

use App\Models\Card;
use App\Models\LinkShareSetting;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\LinkShareSetting\Exceptions\LinkShareSettingTypeNotSupported;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use App\Modules\Permission\PermissionRepository;

class LinkShareSettingRepository
{
    private LinkShareSetting $linkShareSetting;
    private LinkShareTypeRepository $linkShareTypeRepo;
    private PermissionRepository $permissionRepo;
    private CapabilityRepository $capabilityRepo;

    public function __construct(
        LinkShareSetting $linkShareSetting,
        LinkShareTypeRepository $linkShareTypeRepo,
        PermissionRepository $permissionRepo,
        CapabilityRepository $capabilityRepo
    ) {
        $this->linkShareSetting = $linkShareSetting;
        $this->linkShareTypeRepo = $linkShareTypeRepo;
        $this->permissionRepo = $permissionRepo;
        $this->capabilityRepo = $capabilityRepo;
    }

    public function getClassPath($type)
    {
        if ($type instanceof Card) {
            return Card::class;
        }
        throw new LinkShareSettingTypeNotSupported('The type given for LinkShareSetting is not supported.');
    }

    public function createIfNew($type, string $shareType, string $capability)
    {
        $path = $this->getClassPath($type);
        $linkShareType = $this->linkShareTypeRepo->get($shareType);
        $capabilityId = $this->capabilityRepo->get($capability)->id;
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

    public function createAnyoneOrganizationIfNew($type, string $capability)
    {
        return $this->createIfNew($type, LinkShareTypeRepository::ANYONE_ORGANIZATION, $capability);
    }

    public function createAnyoneIfNew($type, string $capability)
    {
        return $this->createIfNew($type, LinkShareTypeRepository::ANYONE, $capability);
    }

    public function createPublicIfNew($type, string $capability)
    {
        // If it's public it should be discoverable by anyone as well
        $this->permissionRepo->createAnyone($type, $capability);

        return $this->createIfNew($type, LinkShareTypeRepository::PUBLIC_SHARE, $capability);
    }
}
