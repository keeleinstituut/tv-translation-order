<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\MediaCreateRequest;
use App\Http\Requests\API\MediaDeleteRequest;
use App\Http\Requests\API\MediaDownloadRequest;
use App\Http\Requests\MediaUpdateRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Project;
use App\Models\ProjectReviewRejection;
use App\Models\SubProject;
use Auth;
use Illuminate\Auth\Access\AuthorizationException;
use AuditLogClient\Models\AuditLoggable;
use AuditLogClient\Services\AuditLogMessageBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use App\Http\OpenApiHelpers as OAH;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MediaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * @throws Throwable
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

            $affectedEntities = $filesData
                ->map(fn ($file) => $this->determineEntityAndCollectionName($file['reference_object_type'], $file['reference_object_id'], $file['collection']))
                ->mapSpread(fn (AuditLoggable $entity) => $entity)
                ->unique();

            $data = $this->auditLogPublisher->publishModifyObjectsAfterAction(
                $affectedEntities,
                function () use ($filesData) {
                    return $filesData->map(function ($file) {
                        $content = $file['content'];
                        [$entity, $collectionOwnerEntity, $collectionName] = $this->determineEntityAndCollectionName($file['reference_object_type'], $file['reference_object_id'], $file['collection']);
                        if (empty($entity)) {
                            abort(404, $file['reference_object_type'] . ' entity not found by ID ' . $file['reference_object_id']);
                        }

                        $ability = $this->determineAuthorizationAbility($entity, $file['collection']);
                        $this->authorize($ability, [$entity, data_get($file, 'assignment_id')]);

                        if (data_get($file, 'collection') === Project::HELP_FILES_COLLECTION) {
                            $customProperties = [
                                'type' => data_get($file, 'help_file_type'),
                                'institution_user_id' => Auth::user()->institutionUserId
                            ];
                        } else {
                            $customProperties = [
                                'assignment_id' => data_get($file, 'assignment_id'),
                                'institution_user_id' => Auth::user()->institutionUserId
                            ];
                        }

                        /** @var Media $newMedia */
                        $newMedia = $collectionOwnerEntity->addMedia($content)
                            ->withCustomProperties($customProperties)
                            ->toMediaCollection($collectionName);

                        /** Moving new project source file to all subprojects */
                        if (data_get($file, 'collection') === Project::SOURCE_FILES_COLLECTION && $entity instanceof Project) {
                            $entity->subProjects->each(function (SubProject $subProject) use ($newMedia, $entity) {
                                $subProjectSourceFile = $newMedia->copy($entity, $subProject->file_collection);
                                $newMedia->copies()->save($subProjectSourceFile);
                            });
                        }

                        $newMedia->load('assignment.jobDefinition');
                        $newMedia->load('institutionUser');
                        return $newMedia;
                    });
                }
            );

            return MediaResource::collection($data);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/media/{id}',
        summary: 'Update the media.Currently can be used only for updating of the help files types.',
        requestBody: new OAH\RequestBody(MediaUpdateRequest::class),
        tags: ['Media'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: MediaResource::class, description: 'Updated Media', response: Response::HTTP_OK)]
    public function update(MediaUpdateRequest $request): MediaResource
    {
        return DB::transaction(function () use ($request) {
            $media = Media::findOrFail($request->route('id'));

            if ($media->collection_name !== Project::HELP_FILES_COLLECTION) {
                abort(Response::HTTP_BAD_REQUEST, 'Only help files can be updated');
            }

            [$entity, ,] = $this->determineEntityAndCollectionName(
                $media->model_type,
                $media->model_id,
                $media->collection_name
            );

            $ability = $this->determineAuthorizationAbility($entity, $media->collection_name);
            $this->authorize($ability, $entity);

            $media->setCustomProperty('type', $request->validated('help_file_type'));
            $media->saveOrFail();

            return MediaResource::make($media);
        });

    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
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

            $affectedEntities = $filesData
                ->map(fn ($file) => $this->determineEntityAndCollectionName($file['reference_object_type'], $file['reference_object_id'], $file['collection']))
                ->mapSpread(fn (AuditLoggable $entity) => $entity)
                ->unique();

            $data = $this->auditLogPublisher->publishModifyObjectsAfterAction(
                $affectedEntities,
                function () use ($filesData) {
                    return $filesData->map(function ($file) {
                        [$entity, $collectionOwnerEntity, $collectionName] = $this->determineEntityAndCollectionName($file['reference_object_type'], $file['reference_object_id'], $file['collection']);
                        if (empty($entity)) {
                            abort(404, $file['reference_object_type'] . ' entity not found by ID ' . $file['reference_object_id']);
                        }

                        $ability = $this->determineAuthorizationAbility($entity, $file['collection']);
                        $media = Media::getModel()
                            ->where('model_id', $collectionOwnerEntity->id)
                            ->where('model_type', $collectionOwnerEntity::class)
                            ->where('collection_name', $collectionName)
                            ->where('id', $file['id'])
                            ->first() ?? abort(404);

                        $this->authorize($ability, [$entity, $media->getCustomProperty('assignment_id')]);

                        $media->delete();
                        return $media;
                    });
                }
            );

            return MediaResource::collection($data);
        });
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/media/download',
        description: 'The next pairs of query parameters are available (reference_object_type, collection): ("project", "source"), ("project", "help"), ("subproject", "source"), ("subproject", "final"), ("review", "review"). For downloading review files pass reference_object_id as ID of the review',
        tags: ['Media'],
        parameters: [
            new OA\QueryParameter(name: 'collection', schema: new OA\Schema(type: 'string', enum: ['source', 'help', 'review', 'final'])),
            new OA\QueryParameter(name: 'reference_object_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'reference_object_type', schema: new OA\Schema(type: 'string', enum: ['project', 'subproject', 'review'])),
            new OA\QueryParameter(name: 'id', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    public function download(MediaDownloadRequest $request)
    {
        $params = collect($request->validated());

        [$entity, $collectionOwnerEntity, $collectionName] = $this->determineEntityAndCollectionName($params['reference_object_type'], $params['reference_object_id'], $params['collection']);

        $this->authorize('downloadMedia', $entity);

        $media = Media::getModel()
            ->where('model_id', $collectionOwnerEntity->id)
            ->where('model_type', $collectionOwnerEntity::class)
            ->where('collection_name', $collectionName)
            ->where('id', $params['id'])
            ->first() ?? abort(404);

        $this->auditLogPublisher->publish(
            AuditLogMessageBuilder::makeUsingJWT()->toDownloadProjectFileEvent(
                $media->id,
                $collectionOwnerEntity->id,
                $collectionOwnerEntity->ext_id,
                $media->file_name
            )
        );

        return $media->toResponse($request);
    }

    private function determineEntityAndCollectionName(string $referenceObjectType, string $referenceObjectId, $collection): ?array
    {
        $entityClass = match ($referenceObjectType) {
            'project', Project::class => Project::class,
            'subproject', SubProject::class => SubProject::class,
            'review', ProjectReviewRejection::class => ProjectReviewRejection::class,
            default => null,
        };

        if (empty($entityClass)) {
            return null;
        }

        /** @var Project|SubProject|ProjectReviewRejection|null $entity */
        if (empty($entity = $entityClass::find($referenceObjectId))) {
            return null;
        }

        return match ([$entityClass, $collection]) {
            [Project::class, 'source'] => [$entity, $entity, Project::SOURCE_FILES_COLLECTION],
            [Project::class, 'help'] => [$entity, $entity, Project::HELP_FILES_COLLECTION],
            [ProjectReviewRejection::class, 'review'], [SubProject::class, 'source'] => [$entity, $entity->project, $entity->file_collection],
            [SubProject::class, 'final'] => [$entity, $entity->project, $entity->file_collection_final],
            default => null,
        };
    }

    private function determineAuthorizationAbility($entity, $collectionName): ?string
    {
        return match ([$entity::class, $collectionName]) {
            [Project::class, 'source'], [SubProject::class, 'source'] => 'editSourceFiles',
            [Project::class, 'help'] => 'editHelpFiles',
            [SubProject::class, 'final'] => 'editFinalFiles',
            default => null,
        };
    }
}
