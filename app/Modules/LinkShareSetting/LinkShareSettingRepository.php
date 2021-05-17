<?php

namespace App\Modules\LinkShareSetting;

use App\Models\Card;
use App\Models\Collection;
use App\Models\LinkShareSetting;
use App\Modules\Capability\CapabilityRepository;
use App\Modules\LinkShareSetting\Exceptions\CapabilityNotSupported;
use App\Modules\LinkShareSetting\Exceptions\LinkShareSettingTypeNotSupported;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use App\Modules\Permission\PermissionRepository;

class LinkShareSettingRepository
{
    private LinkShareTypeRepository $linkShareTypeRepo;
    private PermissionRepository $permissionRepo;
    private CapabilityRepository $capabilityRepo;

    public function __construct(
        LinkShareTypeRepository $linkShareTypeRepo,
        PermissionRepository $permissionRepo,
        CapabilityRepository $capabilityRepo
    ) {
        $this->linkShareTypeRepo = $linkShareTypeRepo;
        $this->permissionRepo = $permissionRepo;
        $this->capabilityRepo = $capabilityRepo;
    }

    public function getClassPath($type)
    {
        if ($type instanceof Card) {
            return Card::class;
        }
        if ($type instanceof Collection) {
            return Collection::class;
        }
        throw new LinkShareSettingTypeNotSupported('The type given for LinkShareSetting is not supported.');
    }

    /**
     * @param $type
     *
     * @throws CapabilityNotSupported
     * @throws LinkShareSettingTypeNotSupported
     *
     * @return false
     */
    public function createIfNew($type, string $shareType, string $capability)
    {
        $path = $this->getClassPath($type);
        $linkShareType = $this->linkShareTypeRepo->get($shareType);
        if (! $linkShareType) {
            throw new LinkShareSettingTypeNotSupported('The permission type "'.$shareType.'" is not a proper sharing type. Please check the documentation and try again.');
        }

        $capabilityId = $this->capabilityRepo->get($capability)->id;
        if (! $capabilityId) {
            throw new CapabilityNotSupported('The permission capability "'.$capability.'" is not a proper capability type. Please check the documentation and try again.');
        }

        $settingExists = LinkShareSetting::where([
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

    /**
     * deprecated - will replace with permissions workspace - because you might want to share with more
     * than one workspace.
     *
     * @param $type
     */
    public function createAnyoneWorkspaceIfNew($type, string $capability)
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
