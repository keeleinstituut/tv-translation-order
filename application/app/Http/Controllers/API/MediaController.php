<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\MediaCreateRequest;
use App\Http\Requests\API\MediaDeleteRequest;
use App\Http\Requests\API\MediaDownloadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use App\Http\OpenApiHelpers as OAH;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/media/bulk',
        description: "
            Example query as a curl to better demonstrate accepted queryparams

            curl --location 'http://localhost:8001/api/media/bulk' \\
            --header 'Accept: application/json' \\
            --form 'files[0][content]=@\"/path/to/file/ipsum.txt\"' \\
            --form 'files[0][reference_object_id]=\"9a759d06-f720-41a7-9d94-d2c8b65464c0\"' \\
            --form 'files[0][reference_object_type]=\"subproject\"' \\
            --form 'files[0][collection]=\"final\"'
        ",
        requestBody: new OAH\RequestBody(MediaCreateRequest::class),
        tags: ['Media'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: MediaResource::class, response: Response::HTTP_CREATED)]
    public function bulkStore(MediaCreateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $filesData = collect($params->get('files'));

            $data = $filesData->map(function ($file) {
                $content = $file['content'];
                [$entity, $collectionOwnerEntity, $collectionName] = $this->determineEntityAndCollectionName($file['reference_object_type'], $file['reference_object_id'], $file['collection']);
                $ability = $this->determineAuthorizationAbility($entity, $file['collection']);

                $this->authorize($ability, $entity);

                $media = $collectionOwnerEntity->addMedia($content)
                    ->toMediaCollection($collectionName);
                return $media;
            });

            return MediaResource::collection($data);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/media/bulk',
        requestBody: new OAH\RequestBody(MediaDeleteRequest::class),
        tags: ['Media'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: MediaResource::class, response: Response::HTTP_CREATED)]
    public function bulkDestroy(MediaDeleteRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $filesData = collect($params->get('files'));

            $data = $filesData->map(function ($file) {
                [$entity, $collectionOwnerEntity, $collectionName] = $this->determineEntityAndCollectionName($file['reference_object_type'], $file['reference_object_id'], $file['collection']);
                $ability = $this->determineAuthorizationAbility($entity, $file['collection']);

                $this->authorize($ability, $entity);

                $media = Media::getModel()
                    ->where('model_id', $collectionOwnerEntity->id)
                    ->where('model_type', $collectionOwnerEntity::class)
                    ->where('collection_name', $collectionName)
                    ->where('id', $file['id'])
                    ->first() ?? abort(404);

                $media->delete();
                return $media;
            });

            return MediaResource::collection($data);
        });
    }

    #[OA\Get(
        path: '/media/download',
        tags: ['Media'],
        parameters: [
            new OA\QueryParameter(name: 'collection', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'reference_object_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'reference_object_type', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'id', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    public function download(MediaDownloadRequest $request)
    {
        $params = collect($request->validated());

        [$entity, $collectionOwnerEntity, $collectionName] = $this->determineEntityAndCollectionName($params['reference_object_type'], $params['reference_object_id'], $params['collection']);

        $this->authorize('view', $entity);

        $media = Media::getModel()
            ->where('model_id', $collectionOwnerEntity->id)
            ->where('model_type', $collectionOwnerEntity::class)
            ->where('collection_name', $collectionName)
            ->where('id', $params['id'])
            ->first() ?? abort(404);


        return $media->toResponse($request);
    }

    private function determineEntityAndCollectionName(string $referenceObjectType, string $referenceObjectId, $collection)
    {
        $entityClass = match ($referenceObjectType) {
            'project' => Project::class,
            'subproject' => SubProject::class,
            default => null,
        };

        $entity = $entityClass::find($referenceObjectId);

        return match ([$referenceObjectType, $collection]) {
            ['project', 'source'] => [$entity, $entity, Project::SOURCE_FILES_COLLECTION],
            ['subproject', 'source'] => [$entity, $entity->project, $entity->file_collection],
            ['subproject', 'final'] => [$entity, $entity->project, $entity->file_collection_final],
            default => null,
        };
    }

    private function determineAuthorizationAbility($entity, $collectionName) {
        return match ([$entity::class, $collectionName]) {
            [Project::class, 'source'] => 'editSourceFiles',
            [SubProject::class, 'source'] => 'editSourceFiles',
            [SubProject::class, 'final'] => 'editFinalFiles',
            default => null,
        };
    }
}
