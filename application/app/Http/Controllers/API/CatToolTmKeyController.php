<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\CatToolTmCreateRequest;
use App\Http\Resources\API\CatToolTmKeyResource;
use App\Models\CatToolTmKey;
use App\Models\SubProject;
use App\Policies\CatToolTmKeyPolicy;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\OpenApiHelpers as OAH;
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
        path: '/cat-tool/tm-keys/{sub_project_id}',
        summary: 'Get CAT tool TM keys by given sub-project UUID',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolTmKeyResource::class, description: 'TMs of sub-project')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CatToolTmKey::class);
        $query = self::getBaseQuery()->where('sub_project_id', $request->get('sub_project_id'))
            ->orderBy('created_at');

        return CatToolTmKeyResource::collection($query->get());
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/cat-tool/tm-keys/add',
        summary: 'Add new TM key to the CAT tool',
        requestBody: new OAH\RequestBody(CatToolTmCreateRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: CatToolTmKeyResource::class, description: 'Created CAT tool TM', response: Response::HTTP_CREATED)]
    public function store(CatToolTmCreateRequest $request): CatToolTmKeyResource
    {
        $this->authorize('create', CatToolTmKey::class);
        return DB::transaction(function () use ($request): CatToolTmKeyResource {
            $subProject = SubProject::findOrFail($request->validated('sub_project_id'));

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::InProgress) {
                abort(Response::HTTP_BAD_REQUEST, 'CAT tool setup is in progress, please try again later');
            }

            $tmKey = new CatToolTmKey($request->validated());
            $tmKey->saveOrFail();
            $tmKey->refresh();

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::Created) {
                $subProject->cat()->addTMKey($tmKey);
            }

            return CatToolTmKeyResource::make($tmKey);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/cat-tool/tm-keys/delete/{cat_tool_tm_key_id}',
        summary: 'Mark the CAT tool TM key with the given UUID as deleted',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('cat_tool_tm_key_id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: CatToolTmKeyResource::class, description: 'The CAT tool TM marked as deleted')]
    public function destroy(Request $request)
    {
        return DB::transaction(function () use ($request): CatToolTmKeyResource {
            /** @var CatToolTmKey $tmKey */
            $tmKey = self::getBaseQuery()->findOrFail($request->get('cat_tool_tm_key_id'));
            $subProject = $tmKey->subProject;
            $this->authorize('delete', $tmKey);

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::InProgress) {
                abort(Response::HTTP_BAD_REQUEST, 'CAT tool setup is in progress, please try again later');
            }

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::Created) {
                if ($subProject->catToolTmKeys()->count() === 1) {
                    abort(Response::HTTP_BAD_REQUEST, 'CAT tool should have at least one translation memory');
                }

                $subProject->cat()->deleteTMKey($tmKey);
            }

            $tmKey->deleteOrFail();
            return CatToolTmKeyResource::make($tmKey->refresh());
        });
    }

    private static function getBaseQuery(): Builder
    {
        return CatToolTmKey::withGlobalScope('policy', CatToolTmKeyPolicy::scope());
    }
}
