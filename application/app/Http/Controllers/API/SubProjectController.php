<?php

namespace App\Http\Controllers\API;

use App\Enums\JobKey;
use App\Enums\SubProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\SetProjectFinalFilesRequest;
use App\Http\Requests\API\SubProjectListRequest;
use App\Http\Requests\SubProjectUpdateRequest;
use App\Http\Resources\API\SubProjectResource;
use App\Http\Resources\API\VolumeResource;
use App\Models\Assignment;
use App\Models\Media;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
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
            'activeJobDefinition',
            'project.clientInstitutionUser',
            'project.tags',
        ]);

        if ($param = $params->get('ext_id')) {
            $query = $query->where('ext_id', 'ilike', "%$param%");
        }

        if ($param = $params->get('project_id')) {
            $query = $query->where('project_id', $param);
        }

        if ($param = $params->get('status')) {
            $query = $query->whereIn('status', $param);
        }

        if ($param = $params->get('type_classifier_value_id')) {
            $query = $query->whereRelation(
                'project',
                fn(Builder $projectQuery) => $projectQuery->whereIn('type_classifier_value_id', $param)
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
        
        $query = $query
            ->join('projects', 'projects.id', '=', 'sub_projects.project_id')
            ->join('entity_cache.cached_institution_users', 'projects.client_institution_user_id', '=', 'cached_institution_users.id')
            ->select('sub_projects.*')
            ->selectRaw("concat(cached_institution_users.user->>'forename', ' ', cached_institution_users.user->>'surname') as project_client_institution_user_name"); // For ordering by client's name

        $sortBy = $params->get('sort_by');
        $sortOrder = $params->get('sort_order', 'desc');

        switch ($sortBy) {
            case 'price':
                $query = $query->orderBy('price', $sortOrder);
                break;

            case 'deadline_at':
                $query = $query->orderBy('deadline_at', $sortOrder);
                break;

            case 'created_at':
                $query = $query->orderBy('created_at', $sortOrder);
                break;

            case 'project.event_start_at':
                $query = $query->orderBy('projects.event_start_at', $sortOrder);
                break;

            case 'status':
                $query = $query->orderBy('status', $sortOrder);
                break;

            case 'project.reference_number':
                $query = $query->orderBy('projects.reference_number', $sortOrder);
                break;

            case 'ext_id':
                $query = $query->orderBy('ext_id', $sortOrder);
                break;

            case 'clientInstitutionUser.name':
                $query = $query->orderBy('project_client_institution_user_name', $sortOrder);
                break;
            
            default:
                $query = $query->orderBy('created_at', $sortOrder);
                break;
        }

        $data = $query->paginate($params->get('per_page', 10));

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
            'finalFiles.assignment.jobDefinition',
            'finalFiles.copies',
            'project.typeClassifierValue.projectTypeConfig.jobDefinitions',
            'assignments.candidates.vendor.institutionUser',
            'assignments.assignee.institutionUser',
            'assignments.volumes.institutionDiscount',
            'assignments.catToolJobs',
            'assignments.jobDefinition',
            'catToolJobs',
            'activeJobDefinition'
        ])->findOrFail($id);

        $this->authorize('view', $subProject);

        return new SubProjectResource($subProject);
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/subprojects/{id}',
        summary: 'Update subproject',
        requestBody: new OAH\RequestBody(SubProjectUpdateRequest::class),
        tags: ['Sub-projects'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: SubProjectResource::class, description: 'Sub-project resource', response: Response::HTTP_OK)]
    public function update(SubProjectUpdateRequest $request): SubProjectResource
    {
        return DB::transaction(function () use ($request) {
            /** @var SubProject $subProject */
            $subProject = self::getBaseQuery()->findOrFail($request->route('id'));

            $this->authorize('update', $subProject);

            $subProject->fill($request->validated())->saveOrFail();

            return SubProjectResource::make($subProject->refresh());
        });
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
    #[OAH\ResourceResponse(dataRef: SubProjectResource::class, description: 'Sub-project resource', response: Response::HTTP_OK)]
    public function startWorkflow(string $id): SubProjectResource
    {
        /** @var SubProject $subProject */
        $subProject = self::getBaseQuery()->findOrFail($id);

        $this->authorize('startWorkflow', $subProject);

        if ($subProject->workflow()->isStarted()) {
            abort(400, 'Workflow is already started for the sub-project');
        }

        if ($subProject->status === SubProjectStatus::Cancelled) {
            abort(400, 'Not possible to start workflow for the cancelled sub-project');
        }

        $hasAssignmentWithoutCandidates = $subProject->assignments()
            ->whereRelation('jobDefinition', function (Builder $jobDefinitionQuery) {
                $jobDefinitionQuery->whereNot('job_key', JobKey::JOB_OVERVIEW);
            })->whereDoesntHave('candidates')->exists();

        if ($hasAssignmentWithoutCandidates) {
            abort(400, 'Sub-project contains job(s) without candidates');
        }

        $hasAssignmentWithoutDeadline = $subProject->assignments()
            ->whereNull('deadline_at')->exists();

        if ($hasAssignmentWithoutDeadline) {
            abort(400, 'Sub-project contains assignments without deadline');
        }

        $subProject->workflow()->start();
        $subProject->refresh();
        return SubProjectResource::make($subProject);
    }


    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/subprojects/{id}/set-project-final-files',
        summary: 'Set project final files based on subproject final files',
        tags: ['Sub-projects'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: SubProjectResource::class, description: 'Sub-project resource', response: Response::HTTP_OK)]
    public function setProjectFinalFiles(SetProjectFinalFilesRequest $request): SubProjectResource
    {
        /** @var SubProject $subProject */
        $subProject = self::getBaseQuery()->findOrFail($request->route('id'));
        $this->authorize('markFilesAsProjectFinalFiles', $subProject);

        return DB::transaction(function () use ($subProject, $request) {
            $subProject->syncFinalFilesWithProject(
                $request->validated('final_file_id')
            );
            $subProject->load([
                'finalFiles.copies',
                'finalFiles.assignment',
            ]);
            return SubProjectResource::make($subProject);
        });
    }


    /**
     * List combinations of languages used for available projects
     *
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/subprojects/languages',
        description: 'Project\'s languages are based on related sub-projects',
        summary: 'Get all combinations of languages used on available projects',
        tags: ['Sub-projects'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'List of unique language combinations',
        content: new OA\JsonContent(
            required: ['data'],
            properties: [
                new OA\Property(
                    property: 'data', type: 'array', items: new OA\Items(type: 'string', example: 'srcLangUUID:dstLangUUID')
                ),
            ]
        )
    )]
    public function getLanguageCombinations(): JsonResponse
    {
        $this->authorize('viewAny', [SubProject::class, false]);

        $languageCombinations = $this->getBaseQuery()->select('source_language_classifier_value_id', 'destination_language_classifier_value_id')
            ->distinct()
            ->get()
            ->map(fn($subProject) => $subProject->source_language_classifier_value_id . ':' . $subProject->destination_language_classifier_value_id);

        return response()->json(['data' => $languageCombinations]);
    }

    private static function getBaseQuery(): Builder
    {
        return SubProject::withGlobalScope('policy', SubProjectPolicy::scope());
    }
}
