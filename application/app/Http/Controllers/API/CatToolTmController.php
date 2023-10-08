<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\CatToolTmCreateRequest;
use App\Http\Resources\API\CatToolTmResource;
use App\Models\CatToolTm;
use App\Models\SubProject;
use App\Policies\CatToolTmPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\OpenApiHelpers as OAH;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CatToolTmController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/cat-tool/tm/{sub_project_id}',
        summary: 'Get CAT tool TMs by given sub-project UUID',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolTmResource::class, description: 'TMs of sub-project')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CatToolTm::class);
        $query = self::getBaseQuery()->where('sub_project_id', $request->get('sub_project_id'))
            ->orderBy('created_at');

        return CatToolTmResource::collection($query->get());
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/cat-tool/tm/add',
        summary: 'Add new TM to the CAT tool',
        requestBody: new OAH\RequestBody(CatToolTmCreateRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: CatToolTmResource::class, description: 'Created CAT tool TM', response: Response::HTTP_CREATED)]
    public function store(CatToolTmCreateRequest $request): CatToolTmResource
    {
        $this->authorize('create', CatToolTm::class);
        return DB::transaction(function () use ($request): CatToolTmResource {
            $subProject = SubProject::findOrFail($request->validated('sub_project_id'));
            $tm = new CatToolTm($request->validated());
            $tm->saveOrFail();
            $tm->refresh();

            if ($subProject->cat()->isCreated()) {
                $subProject->cat()->addTm($tm);
            }

            return CatToolTmResource::make($tm);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/cat-tool/tm/delete/{cat_tool_tm_id}',
        summary: 'Mark the CAT tool TM with the given UUID as deleted',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('cat_tool_tm_id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: CatToolTmResource::class, description: 'The CAT tool TM marked as deleted')]
    public function destroy(Request $request)
    {
        return DB::transaction(function () use ($request): CatToolTmResource {
            /** @var CatToolTm $tm */
            $tm = self::getBaseQuery()->findOrFail($request->get('cat_tool_tm_id'));
            $subProject = $tm->subProject;
            $this->authorize('delete', $tm);
            $tm->deleteOrFail();

            if ($subProject->cat()->isCreated()) {
                if (!$subProject->catToolTms()->exists()) {
                    abort(Response::HTTP_BAD_REQUEST, 'CAT tool should have at least one translation memory');
                }
                $subProject->cat()->deleteTM($tm);
            }

            return CatToolTmResource::make($tm->refresh());
        });
    }

    private static function getBaseQuery(): Builder
    {
        return CatToolTm::withGlobalScope('policy', CatToolTmPolicy::scope());
    }
}
