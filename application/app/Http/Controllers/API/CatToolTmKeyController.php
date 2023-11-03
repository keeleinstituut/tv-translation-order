<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CatToolTmKeysSyncRequest;
use App\Http\Requests\API\CatToolTmKeyToggleIsWritableRequest;
use App\Http\Requests\API\TmKeySubProjectListRequest;
use App\Http\Resources\API\CatToolTmKeyResource;
use App\Http\Resources\API\SubProjectResource;
use App\Models\CatToolTmKey;
use App\Models\SubProject;
use App\Policies\CatToolTmKeyPolicy;
use App\Policies\SubProjectPolicy;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CatToolTmKeyController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/tm-keys/{sub_project_id}',
        summary: 'Get CAT tool TM keys by given sub-project UUID',
        tags: ['TM keys'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolTmKeyResource::class, description: 'TM keys of the sub-project')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->findOrFail($request->route('sub_project_id'));

        $this->authorize('viewAny', [CatToolTmKey::class, $subProject]);

        $query = self::getBaseQuery()->where('sub_project_id', $subProject->id)
            ->orderBy('created_at');

        return CatToolTmKeyResource::collection($query->get());
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/tm-keys/subprojects/{key}',
        summary: 'Get sub-projects by given TM key',
        tags: ['TM keys'],
        parameters: [
            new OA\QueryParameter(name: 'key', description: 'UUID of the tag from the NecTM', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\PaginatedCollectionResponse(itemsRef: SubProjectResource::class, description: 'Sub-projects of the TM key')]
    public function subProjectsIndex(TmKeySubProjectListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAnyByTmKey', SubProject::class);

        $query = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->with('translationDomainClassifierValue')
            ->whereRelation('catToolTmKeys', 'key', $request->route('key'))
            ->orderBy('created_at', 'desc');

        return SubProjectResource::collection(
            $query->paginate(
                $request->get('per_page', 10)
            )
        );
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/tm-keys/sync',
        summary: 'Add new/delete missing TM keys for the sub-project',
        requestBody: new OAH\RequestBody(CatToolTmKeysSyncRequest::class),
        tags: ['TM keys'],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized, new OAH\InvalidTmKeys]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolTmKeyResource::class, description: 'Affected CAT tool TMs', response: Response::HTTP_OK)]
    public function sync(CatToolTmKeysSyncRequest $request): AnonymousResourceCollection
    {
        return DB::transaction(function () use ($request): AnonymousResourceCollection {
            $subProject = $request->getSubProject();
            $this->authorize('sync', [CatToolTmKey::class, $subProject]);

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::InProgress) {
                abort(Response::HTTP_BAD_REQUEST, 'CAT tool setup is in progress, please try again later');
            }

            $existingTmKeys = $subProject->catToolTmKeys()->get()->keyBy('key');
            $receivedTmKeysData = collect($request->validated('tm_keys'))->keyBy('key');

            $this->auditLogPublisher->publishModifyObjectAfterAction(
                $subProject,
                function () use ($existingTmKeys, $receivedTmKeysData, $subProject) {
                    // Create new TM keys
                    $receivedTmKeysData->keys()->diff($existingTmKeys->keys())
                        ->each(function (string $newTmKey) use ($receivedTmKeysData, $subProject) {
                            $newTmKeyData = $receivedTmKeysData->get($newTmKey);
                            (new CatToolTmKey())
                                ->fill([
                                    ...$newTmKeyData,
                                    'sub_project_id' => $subProject->id,
                                ])->saveOrFail();
                        });

                    // Delete missing
                    if (! empty($tmKeysToRemove = $existingTmKeys->keys()->diff($receivedTmKeysData->keys())->toArray())) {
                        CatToolTmKey::whereIn('key', $tmKeysToRemove)
                            ->where('sub_project_id', $subProject->id)
                            ->delete();
                    }

                    if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::Done) {
                        try {
                            $subProject->cat()->setTmKeys();
                        } catch (RequestException $e) {
                            abort(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                        }
                    }
                }
            );

            return CatToolTmKeyResource::collection($subProject->catToolTmKeys()->get());
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/tm-keys/toggle-writable/{id}',
        summary: 'Mark/Unmark TM key as writable for the sub-project',
        requestBody: new OAH\RequestBody(CatToolTmKeyToggleIsWritableRequest::class),
        tags: ['TM keys'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    public function toggleWritable(CatToolTmKeyToggleIsWritableRequest $request): CatToolTmKeyResource
    {
        /** @var CatToolTmKey $tmKey */
        $tmKey = self::getBaseQuery()->findOrFail($request->route('id'));
        $this->authorize('toggleWritable', $tmKey);

        $this->auditLogPublisher->publishModifyObjectAfterAction(
            $tmKey->subProject,
            function () use ($tmKey, $request) {
                $tmKey->fill($request->validated());
                $tmKey->saveOrFail();
            }
        );

        return CatToolTmKeyResource::make($tmKey);
    }

    private static function getBaseQuery(): Builder
    {
        return CatToolTmKey::withGlobalScope('policy', CatToolTmKeyPolicy::scope());
    }
}
