<?php

namespace App\Http\Controllers;

use App\Http\Requests\Collection\CreateCollectionRequest;
use App\Http\Requests\Collection\UpdateCollectionRequest;
use App\Modules\Collection\CollectionRepository;
use App\Modules\Collection\CollectionSerializer;
use App\Modules\Collection\Exceptions\CollectionUserIdMustExist;
use App\Modules\LinkShareSetting\Exceptions\CapabilityNotSupported;
use App\Modules\LinkShareSetting\Exceptions\LinkShareSettingTypeNotSupported;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    private CollectionRepository $collectionRepository;
    private CollectionSerializer $collectionSerializer;

    public function __construct(
        CollectionRepository $collectionRepository,
        CollectionSerializer $collectionSerializer
    ) {
        $this->collectionRepository = $collectionRepository;
        $this->collectionSerializer = $collectionSerializer;
    }

    /**
     * @throws CollectionUserIdMustExist
     */
    public function create(CreateCollectionRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $collection = $this->collectionRepository->upsert([
                'user_id'     => $userId,
                'title'       => $request->get('title'),
                'description' => $request->get('description'),
                'permissions' => $request->get('permissions'),
            ]);
        } catch (CapabilityNotSupported | LinkShareSettingTypeNotSupported $exception) {
            return response()->json([
                'error'   => 'bad_request',
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json($this->collectionSerializer->serialize($collection));
    }

    public function get(Request $request, string $id): JsonResponse
    {
        $collection = $this->collectionRepository->findCollection($id);
        if (! $collection) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The collection you requested does not exist.',
            ], 404);
        }

        if ((int) $collection->user_id !== $request->user()->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The collection you requested could not be retrieved because you do not have permission to access it.',
            ], 403);
        }

        return response()->json($this->collectionSerializer->serialize($collection));
    }

    /**
     * @throws CollectionUserIdMustExist
     */
    public function update(UpdateCollectionRequest $request, string $id): JsonResponse
    {
        $collection = $this->collectionRepository->findCollection($id);
        if (! $collection) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The collection you tried to update does not exist.',
            ], 404);
        }

        if ((int) $collection->user_id !== $request->user()->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The collection could not be updated because you do not have permission to update it.',
            ], 403);
        }

        try {
            $collection = $this->collectionRepository->upsert([
                'title'       => $request->get('title'),
                'description' => $request->get('description'),
                'permissions' => $request->get('permissions'),
            ], $collection);
        } catch (CapabilityNotSupported | LinkShareSettingTypeNotSupported $exception) {
            return response()->json([
                'error'   => 'bad_request',
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json($this->collectionSerializer->serialize($collection));
    }

    public function delete(Request $request, string $id)
    {
        $collection = $this->collectionRepository->findCollection($id);
        if (! $collection) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'The collection you tried to delete does not exist.',
            ], 404);
        }

        if ((int) $collection->user_id !== $request->user()->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The collection could not be deleted because you do not have permission to delete it.',
            ], 403);
        }

        $collection->delete();

        return response()->json([], 204);
    }
}
