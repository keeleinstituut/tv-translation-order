<?php

namespace App\Http\Controllers\API;

use App\Enums\SubProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\SubProjectListRequest;
use App\Http\Requests\API\VolumeCreateRequest;
use App\Http\Resources\API\SubProjectResource;
use App\Http\Resources\API\VolumeResource;
use App\Models\Assignment;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SubProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/subprojects',
        description: 'If there are multiple types of filtering conditions, they will be joined with the "AND" operand.',
        summary: 'List and optionally filter sub-projects belonging to the current institution (inferred from JWT)',
        tags: ['Sub-projects'],
        parameters: [
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['price', 'deadline_at', 'created_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
            new OA\QueryParameter(name: 'ext_id', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'only_show_personal_sub_projects', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\QueryParameter(
                name: 'status[]',
                description: 'Filter the result set to projects which have any of the specified statuses. TODO: add filtering on the BE',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: SubProjectStatus::class)
                )
            ),
            new OA\QueryParameter(
                name: 'project_id',
                description: 'Filter the result set of sub-projects that belongs to specified project.',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\QueryParameter(
                name: 'type_classifier_value_id[]',
                description: 'Filter the result set to projects which have any of the specified types.',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uuid')
                )
            ),
            new OA\QueryParameter(
                name: 'language_direction[]',
                description: 'Filter the result set to projects which have any of the specified language directions.',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        description: 'd7719f74-3f27-490f-929d-e2d4954e797e:79c7ed08-501d-463c-a5b5-c8fd7e0c6179',
                        type: 'string',
                        example: 'Two UUIDs of language classifier values separated by a colon (:) character'
                    )
                )
            ),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\PaginatedCollectionResponse(itemsRef: SubProjectResource::class, description: 'Filtered sub-projects of selected project')]
    public function index(SubProjectListRequest $request): AnonymousResourceCollection
    {
        $params = collect($request->validated());

        $showOnlyPersonalProjects = filter_var($params->get('only_show_personal_projects', false), FILTER_VALIDATE_BOOLEAN);

        $this->authorize('viewAny', [SubProject::class, $showOnlyPersonalProjects]);

        $query = self::getBaseQuery()->with([
            'sourceLanguageClassifierValue',
            'destinationLanguageClassifierValue',
            'project.typeClassifierValue',
        ]);

        if ($param = $params->get('ext_id')) {
            $query = $query->where('ext_id', 'ilike', "%$param%");
        }

        if ($param = $params->get('project_id')) {
            $query = $query->where('project_id', $param);
        }

        //        if ($param = $params->get('status')) {
        //            $query = $query->whereIn('status', $param);
        //        }

        if ($param = $params->get('type_classifier_value_id')) {
            $query = $query->whereRelation(
                'project',
                fn (Builder $projectQuery) => $projectQuery->whereIn('type_classifier_value_id', $param)
            );
        }

        if ($params->get('language_direction')) {
            $query = $query->hasAnyOfLanguageDirections($request->getLanguagesZippedByDirections());
        }

        if ($showOnlyPersonalProjects) {
            $query = $query->whereRelation('project', function (Builder $query) {
                $query
                    ->where('manager_institution_user_id', Auth::user()->institutionUserId)
                    ->orWhere('client_institution_user_id', Auth::user()->institutionUserId);
            });
        }

        $data = $query->orderBy(
            $request->validated('sort_by', 'created_at'),
            $request->validated('sort_order', 'asc')
        )->paginate($params->get('per_page', 10));

        return SubProjectResource::collection($data);
    }

    /**
     * Display the specified resource.
     *
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/subprojects/{id}',
        tags: ['Sub-projects'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: SubProjectResource::class, description: 'Sub-project with given UUID')]
    public function show(string $id): SubProjectResource
    {
        $subProject = self::getBaseQuery()->with([
            'sourceLanguageClassifierValue',
            'destinationLanguageClassifierValue',
            'sourceFiles',
            'finalFiles',
            'project.typeClassifierValue.projectTypeConfig.jobDefinitions',
            'assignments.candidates.vendor.institutionUser',
            'assignments.assignee.institutionUser',
            'assignments.volumes.institutionDiscount',
            'assignments.catToolJobs',
            'assignments.jobDefinition',
            'catToolJobs',
        ])->findOrFail($id);

        $this->authorize('view', $subProject);

        return new SubProjectResource($subProject);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/subprojects/{id}/start-workflow',
        summary: 'Start sub-project workflow',
        tags: ['Sub-projects'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VolumeResource::class, description: 'Created volume', response: Response::HTTP_OK)]
    public function startWorkflow(string $id): SubProjectResource
    {
        /** @var SubProject $subProject */
        $subProject = self::getBaseQuery()->findOrFail($id);

        $this->authorize('startWorkflow', $subProject);

        if ($subProject->workflowStarted()) {
            abort(400, 'Workflow is already started for the sub-project');
        }

        $hasAssignmentWithoutCandidates = $subProject->assignments()
            ->whereDoesntHave('candidates')->exists();

        if ($hasAssignmentWithoutCandidates) {
            abort(400, 'Sub-project contains job(s) without candidates');
        }

        $subProject->project->workflow()->startSubProjectWorkflow($subProject);

        return SubProjectResource::make($subProject);
    }

    private static function getBaseQuery(): Builder
    {
        return SubProject::withGlobalScope('policy', SubProjectPolicy::scope());
    }
}
