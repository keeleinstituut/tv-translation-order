<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectCreateRequest;
use App\Http\Requests\API\ProjectListRequest;
use App\Http\Resources\API\ProjectResource;
use App\Http\Resources\API\ProjectSummaryResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Policies\ProjectPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/projects',
        description: 'If there are multiple types of filtering conditions, they will be joined with the "AND" operand.',
        summary: 'List and optionally filter projects belonging to the current institution (inferred from JWT)',
        parameters: [
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['cost', 'deadline_at', 'created_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
            new OA\QueryParameter(name: 'ext_id', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'only_show_personal_projects', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\QueryParameter(
                name: 'statuses',
                description: 'Filter the result set to projects which have any of the specified statuses.',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        description: 'TODO (computation/enumeration of statuses is unclear for now)',
                        type: 'string',
                        enum: [null]
                    )
                )
            ),
            new OA\QueryParameter(
                name: 'type_classifier_value_ids',
                description: 'Filter the result set to projects which have any of the specified types.',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uuid')
                )
            ),
            new OA\QueryParameter(
                name: 'tag_ids',
                description: 'Filter the result set to projects which have any of the specified tags.',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uuid')
                )
            ),
            new OA\QueryParameter(
                name: 'language_directions',
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
    #[OAH\PaginatedCollectionResponse(itemsRef: ProjectSummaryResource::class, description: 'Filtered projects of current institution')]
    public function index(ProjectListRequest $request): AnonymousResourceCollection
    {
        $showOnlyPersonalProjects = filter_var($request->validated('only_show_personal_projects', true), FILTER_VALIDATE_BOOLEAN);
        $this->authorize('viewAny', [Project::class, $showOnlyPersonalProjects]);

        $paginatedQuery = static::getBaseQuery()
            ->orderBy($request->validated('sort_by', 'created_at'), $request->validated('sort_order', 'asc'))
            ->when($request->has('ext_id'), function (Builder $builder) use ($request) {
                $builder->where(
                    'ext_id',
                    'ilike',
                    '%'.$request->validated('ext_id').'%'
                );
            })
            ->when($showOnlyPersonalProjects, function (Builder $builder) {
                $builder->where(function (Builder $projectClause) {
                    $projectClause
                        ->where('manager_institution_user_id', Auth::user()->institutionUserId)
                        ->orWhere('client_institution_user_id', Auth::user()->institutionUserId);
                });
            })
            ->when(filled($request->validated('statuses')), function (Builder $builder) {
                // TODO: Filter by statuses, ideally by creating a scopeStatuses method in Project
            })
            ->when(filled($request->validated('type_classifier_value_ids')), function (Builder $builder) use ($request) {
                $builder->whereIn('type_classifier_value_id', $request->validated('type_classifier_value_ids'));
            })
            ->when(filled($request->validated('tag_ids')), function (Builder $builder) use ($request) {
                $builder->whereHas('tags', function (Builder $tagClause) use ($request) {
                    $tagClause->whereIn('tags.id', $request->validated('tag_ids'));
                });
            })
            ->when(filled($request->validated('language_directions')), function (Builder $builder) use ($request) {
                $builder->hasAnyOfLanguageDirections($request->getLanguagesZippedByDirections());
            })
            ->with([
                'typeClassifierValue',
                'tags',
                'subProjects',
            ])
            ->paginate(perPage: $request->validated('per_page', 10), page: $request->validated('page', 1))
            ->appends($request->validated());

        return ProjectSummaryResource::collection($paginatedQuery);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/projects',
        summary: 'Create a new project',
        requestBody: new OAH\RequestBody(ProjectCreateRequest::class),
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Created project', response: Response::HTTP_CREATED)]
    public function store(ProjectCreateRequest $request): ProjectResource
    {
        return DB::transaction(function () use ($request) {
            $projectTypeConfig = ProjectTypeConfig::where('type_classifier_value_id', $request->validated('type_classifier_value_id'))->firstOrFail();

            $project = Project::make([
                'institution_id' => Auth::user()->institutionId,
                'type_classifier_value_id' => $request->validated('type_classifier_value_id'),
                'translation_domain_classifier_value_id' => $request->validated('translation_domain_classifier_value_id'),
                'reference_number' => $request->validated('reference_number'),
                'manager_institution_user_id' => $request->validated('manager_institution_user_id'),
                'client_institution_user_id' => $request->validated('client_institution_user_id') ?? Auth::user()->institutionUserId,
                'deadline_at' => $request->validated('deadline_at'),
                'comments' => $request->validated('comments'),
                'event_start_at' => $request->validated('event_start_at'),
                'workflow_template_id' => $projectTypeConfig->workflow_process_definition_id,
            ]);

            $this->authorize('create', $project);

            $project->saveOrFail();

            collect($request->validated('source_files', []))
                ->each(function (UploadedFile $file) use ($project) {
                    $project->addMedia($file)->toMediaCollection(Project::SOURCE_FILES_COLLECTION);
                });

            collect($request->validated('help_files', []))
                ->zip($request->validated('help_file_types', []))
                ->eachSpread(function (UploadedFile $file, string $type) use ($project) {
                    $project->addMedia($file)
                        ->withCustomProperties(['type' => $type])
                        ->toMediaCollection(Project::HELP_FILES_COLLECTION);
                });

            $project->initSubProjects(
                ClassifierValue::findOrFail($request->validated('source_language_classifier_value_id')),
                ClassifierValue::findMany($request->validated('destination_language_classifier_value_ids'))
            );

            $project->workflow()->startProcessInstance();

            return new ProjectResource($project->refresh()->load('media', 'managerInstitutionUser', 'clientInstitutionUser', 'typeClassifierValue', 'translationDomainClassifierValue', 'subProjects'));
        });
    }

    /**
     * Display the specified resource.
     *
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/projects/{id}',
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Project with given UUID')]
    public function show(string $id): ProjectResource
    {
        $project = static::getBaseQuery()->with([
            'media',
            'managerInstitutionUser',
            'clientInstitutionUser',
            'typeClassifierValue',
            'translationDomainClassifierValues',
            'subProjects',
        ])->findOrFail($id);

        $this->authorize('view', $project);

        return new ProjectResource($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public static function getBaseQuery(): Builder
    {
        return Project::getModel()->withGlobalScope('policy', ProjectPolicy::scope());
    }
}
