<?php

namespace App\Modules\Collection;

use App\Models\Collection;
use App\Modules\Collection\Exceptions\CollectionUserIdMustExist;
use App\Modules\LinkShareSetting\Exceptions\CapabilityNotSupported;
use App\Modules\LinkShareSetting\Exceptions\LinkShareSettingTypeNotSupported;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use Illuminate\Support\Facades\DB;

class CollectionRepository
{
    private LinkShareSettingRepository $linkShareSettingRepository;

    public function __construct(LinkShareSettingRepository $linkShareSettingRepository)
    {
        $this->linkShareSettingRepository = $linkShareSettingRepository;
    }

    /**
     * @throws CapabilityNotSupported
     * @throws LinkShareSettingTypeNotSupported
     */
    private function savePermissions(Collection $collection, ?array $permissions = []): void
    {
        DB::transaction(function () use ($collection, $permissions) {
            $collection->linkShareSetting()->delete();
            if (empty($permissions)) {
                return;
            }

            foreach ($permissions as $permission => $capability) {
                $this->linkShareSettingRepository->createIfNew($collection, $permission, $capability);
            }
        });
    }

    /**
     * @throws CapabilityNotSupported
     * @throws CollectionUserIdMustExist
     * @throws LinkShareSettingTypeNotSupported
     */
    public function upsert(array $fields, ?Collection $collection = null): Collection
    {
        $newFields = collect($fields);

        // We are auto asigning a token we never want to set one manually
        $newFields->forget('token');

        if ($collection) {
            $collection->update($newFields->toArray());
            $this->savePermissions($collection, $fields['permissions'] ?? []);

            return $collection;
        }

        if (! $newFields->get('user_id')) {
            throw new CollectionUserIdMustExist('You must include the user_id field when creating a new card.');
        }

        $newFields->put('token', bin2hex(random_bytes(24)));

        $collection = Collection::create($newFields->toArray());
        $this->savePermissions($collection, $fields['permissions'] ?? []);

        return $collection;
    }
}
