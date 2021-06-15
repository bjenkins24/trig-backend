<?php

namespace App\Modules\Collection;

use App\Models\Collection;
use App\Models\CollectionCard;
use App\Models\CollectionHiddenTag;
use App\Models\User;
use App\Modules\Collection\Exceptions\CollectionUserIdMustExist;
use App\Modules\LinkShareSetting\Exceptions\CapabilityNotSupported;
use App\Modules\LinkShareSetting\Exceptions\LinkShareSettingTypeNotSupported;
use App\Modules\LinkShareSetting\LinkShareSettingRepository;
use App\Modules\LinkShareType\LinkShareTypeRepository;
use Illuminate\Support\Collection as IlluminateCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CollectionRepository
{
    private LinkShareSettingRepository $linkShareSettingRepository;
    private LinkShareTypeRepository $linkShareTypeRepository;

    public function __construct(LinkShareSettingRepository $linkShareSettingRepository, LinkShareTypeRepository $linkShareTypeRepository)
    {
        $this->linkShareSettingRepository = $linkShareSettingRepository;
        $this->linkShareTypeRepository = $linkShareTypeRepository;
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

            foreach ($permissions as $permission) {
                $this->linkShareSettingRepository->createIfNew($collection, $permission['type'], $permission['capability']);
            }
        });
    }

    public function isViewable(Collection $collection, ?User $user = null): bool
    {
        $publicId = $this->linkShareTypeRepository->get(LinkShareTypeRepository::PUBLIC_SHARE)->id;
        // Public always true
        if ($collection->linkShareSetting()->where('link_share_type_id', $publicId)->exists()) {
            return true;
        }
        // If not public and no user
        if (! $user) {
            return false;
        }
        // Belongs to user
        if ((int) $collection->user_id === (int) $user->id) {
            return true;
        }

        // Otherwise it's not viewable!
        return false;
    }

    /**
     * @param string $id - This is an id OR a token
     */
    public function findCollection(string $id): ?Collection
    {
        if (is_numeric($id)) {
            $collection = Collection::find($id);
        } else {
            $collection = Collection::where(['token' => $id])->first();
        }
        if (! $collection) {
            return null;
        }

        return $collection;
    }

    public function findByUser(string $id): IlluminateCollection
    {
        return Collection::where(['user_id' => $id])->orderBy('id', 'desc')->get();
    }

    public function getTotalCards(Collection $collection): int
    {
        return CollectionCard::where(['collection_id' => $collection->id])->count();
    }

    public function getHiddenTags(Collection $collection): array
    {
        $hiddenTags = [];
        CollectionHiddenTag::where(['collection_id' => $collection->id])->get()->each(function ($hiddenTag) use (&$hiddenTags) {
            $hiddenTags[] = $hiddenTag->tag;
        });

        return $hiddenTags;
    }

    /**
     * @throws Throwable
     */
    private function saveHiddenTags(Collection $collection, array $hiddenTags): void
    {
        DB::transaction(function () use ($collection, $hiddenTags) {
            $collection->collectionHiddenTags()->where(['collection_id' => $collection->id])->delete();
            foreach ($hiddenTags as $hiddenTag) {
                CollectionHiddenTag::create([
                    'tag'           => $hiddenTag,
                    'collection_id' => $collection->id,
                ]);
            }
        });
    }

    /**
     * @throws CapabilityNotSupported
     * @throws CollectionUserIdMustExist
     * @throws LinkShareSettingTypeNotSupported
     * @throws Throwable
     */
    public function upsert(array $fields, ?Collection $collection = null): Collection
    {
        $newFields = collect($fields);

        // We are auto assigning a token we never want to set one manually
        $newFields->forget('token');

        if ($collection) {
            $collection->update($newFields->toArray());
            $this->savePermissions($collection, $fields['permissions'] ?? []);
            $this->saveHiddenTags($collection, $fields['hidden_tags'] ?? []);

            return $collection;
        }

        if (! $newFields->get('user_id')) {
            throw new CollectionUserIdMustExist('You must include the user_id field when creating a new card.');
        }

        $newFields->put('token', bin2hex(random_bytes(24)));

        $collection = Collection::create($newFields->toArray());
        $this->savePermissions($collection, $fields['permissions'] ?? []);
        $this->saveHiddenTags($collection, $fields['hidden_tags'] ?? []);

        return $collection;
    }
}
