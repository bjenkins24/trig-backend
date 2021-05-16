<?php

namespace App\Http\Controllers;

use App\Http\Requests\Collection\CreateCollectionRequest;
use App\Http\Requests\Collection\UpdateCollectionRequest;
use App\Models\Collection;
use App\Modules\Collection\CollectionRepository;
use App\Modules\Collection\CollectionSerializer;
use App\Modules\Collection\Exceptions\CollectionUserIdMustExist;
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

        $collection = $this->collectionRepository->upsert([
            'user_id'     => $userId,
            'title'       => $request->get('title'),
            'description' => $request->get('description'),
            'slug'        => $request->get('slug'),
        ]);

        return response()->json($this->collectionSerializer->serialize($collection));
    }

    public function get(Request $request, string $id): JsonResponse
    {
        $collection = Collection::find($id);

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
        $collection = Collection::find($id);

        if ((int) $collection->user_id !== $request->user()->id) {
            return response()->json([
                'error'   => 'forbidden',
                'message' => 'The collection could not be updated because you do not have permission to access it.',
            ], 403);
        }

        $collection = $this->collectionRepository->upsert([
            'title'       => $request->get('title'),
            'description' => $request->get('description'),
            'slug'        => $request->get('slug'),
        ], $collection);

        return response()->json($this->collectionSerializer->serialize($collection));
    }

    public function delete()
    {
    }
}
