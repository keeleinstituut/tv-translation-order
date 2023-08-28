<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectListRequest;
use App\Http\Resources\API\ProjectResource;
use App\Http\Resources\API\ProjectSummaryResource;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

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
     */
    public function store(Request $request)
    {
        $params = collect($request->all());

        return DB::transaction(function () use ($params) {
            $project = new Project();
            $project->institution_id = $params->get('institution_id');
            $project->type_classifier_value_id = $params->get('type_classifier_value_id');
            $project->reference_number = $params->get('reference_number');
            $project->workflow_template_id = 'Sample-project';
            $project->deadline_at = $params->get('deadline_at');
            $project->save();

            collect($params->get('source_files'))->each(function ($file) use ($project) {
                $project->addMedia($file)
                    ->toMediaCollection(Project::SOURCE_FILES_COLLECTION);
            });

            collect($params->get('help_files', []))->each(function ($file, $i) use ($project, $params) {
                $type = $params->get('help_file_types')[$i];
                $project->addMedia($file)
                    ->withCustomProperties([
                        'type' => $type,
                    ])
                    ->toMediaCollection(Project::HELP_FILES_COLLECTION);
            });

            $project->initSubProjects($params->get('source_language_classifier_value_id'), $params->get('destination_language_classifier_value_id'));
            $project->workflow()->startProcessInstance();

            $project->refresh();
            $project->load('subProjects', 'sourceFiles', 'helpFiles');

            return new ProjectResource($project);
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
