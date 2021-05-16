<?php

namespace App\Modules\Collection;

use App\Models\Collection;
use App\Modules\Collection\Exceptions\CollectionUserIdMustExist;
use Exception;

class CollectionRepository
{
    /**
     * @throws CollectionUserIdMustExist
     * @throws Exception
     */
    public function upsert(array $fields, ?Collection $collection = null): Collection
    {
        $newFields = collect($fields);

        // We are auto asigning a token we never want to set one manually
        $newFields->forget('token');

        if ($collection) {
            $collection->update($newFields->toArray());

            return $collection;
        }

        if (! $newFields->get('user_id')) {
            throw new CollectionUserIdMustExist('You must include the user_id field when creating a new card.');
        }

        $newFields->put('token', bin2hex(random_bytes(24)));

        $collection = Collection::create($newFields->toArray());

        return $collection;
    }
}
