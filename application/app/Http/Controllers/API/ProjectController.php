<?php

namespace App\Http\Controllers\API;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectCreateRequest;
use App\Http\Requests\API\ProjectListRequest;
use App\Http\Resources\API\ProjectResource;
use App\Http\Resources\API\ProjectSummaryResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
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
        tags: ['Projects'],
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
                    items: new OA\Items(type: 'string', enum: ProjectStatus::class)
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
        $params = collect($request->validated());

        $showOnlyPersonalProjects = filter_var($params->get('only_show_personal_projects', true), FILTER_VALIDATE_BOOLEAN);

        $this->authorize('viewAny', [Project::class, $showOnlyPersonalProjects]);

        $query = self::getBaseQuery()
            ->with([
                'typeClassifierValue',
                'tags',
                'subProjects',
                'subProjects.sourceLanguageClassifierValue',
                'subProjects.destinationLanguageClassifierValue',
            ]);

        if ($param = $params->get('ext_id')) {
            $query = $query->where('ext_id', 'ilike', "%$param%");
        }

        if ($param = $params->get('statuses')) {
            $query = $query->whereIn('status', $param);
        }

        if ($param = $params->get('type_classifier_value_ids')) {
            $query = $query->whereIn('type_classifier_value_id', $param);
        }

        if ($param = $params->get('tag_ids')) {
            $query = $query->whereHas('tags', function (Builder $builder) use ($param) {
                $builder->whereIn('tags.id', $param);
            });
        }

        if ($param = $params->get('language_directions')) {
            $query = $query->hasAnyOfLanguageDirections($request->getLanguagesZippedByDirections());
        }

        ////            ->when($showOnlyPersonalProjects, function (Builder $builder) {
        ////                $builder->where(function (Builder $projectClause) {
        ////                    $projectClause
        ////                        ->where('manager_institution_user_id', Auth::user()->institutionUserId)
        ////                        ->orWhere('client_institution_user_id', Auth::user()->institutionUserId);
        ////                });
        ////            })

        $data = $query
            ->orderBy($request->validated('sort_by', 'created_at'), $request->validated('sort_order', 'asc'))
            ->paginate($params->get('per_page', 10));

        return ProjectResource::collection($data);

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
        tags: ['Projects'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Created project', response: Response::HTTP_CREATED)]
    public function store(ProjectCreateRequest $request): ProjectResource
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $project = Project::make([
                'institution_id' => Auth::user()->institutionId,
                'type_classifier_value_id' => $params->get('type_classifier_value_id'),
                'translation_domain_classifier_value_id' => $params->get('translation_domain_classifier_value_id'),
                'reference_number' => $params->get('reference_number'),
                'manager_institution_user_id' => $params->get('manager_institution_user_id'),
                'client_institution_user_id' => $params->get('client_institution_user_id', Auth::user()->institutionUserId),
                'deadline_at' => $params->get('deadline_at'),
                'comments' => $params->get('comments'),
                'event_start_at' => $params->get('event_start_at'),
                'status' => (bool) $params->get('manager_institution_user_id')
                    ? ProjectStatus::Registered
                    : ProjectStatus::New,
                'workflow_template_id' => Config::get('app.workflows.process_definitions.project'),
            ]);

            $this->authorize('create', $project);

            $project->saveOrFail();

            collect($params->get('source_files', []))
                ->each(function (UploadedFile $file) use ($project) {
                    $project->addMedia($file)->toMediaCollection(Project::SOURCE_FILES_COLLECTION);
                });

            collect($params->get('help_files', []))
                ->zip($params->get('help_file_types', []))
                ->eachSpread(function (UploadedFile $file, string $type) use ($project) {
                    $project->addMedia($file)
                        ->withCustomProperties(['type' => $type])
                        ->toMediaCollection(Project::HELP_FILES_COLLECTION);
                });

            $project->initSubProjects(
                ClassifierValue::findOrFail($params->get('source_language_classifier_value_id')),
                ClassifierValue::findMany($params->get('destination_language_classifier_value_ids'))
            );

            // TODO: disabled for now as it can cause some issues with project creation
            //$project->workflow()->startProcessInstance();

            $project->refresh();
            $project->load('media', 'managerInstitutionUser', 'clientInstitutionUser', 'typeClassifierValue', 'translationDomainClassifierValue', 'subProjects');

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
        tags: ['Projects'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Project with given UUID')]
    public function show(string $id): ProjectResource
    {
        $project = static::getBaseQuery()->with([
            'managerInstitutionUser',
            'clientInstitutionUser',
            'typeClassifierValue',
            'translationDomainClassifierValue',
            'subProjects',
            'subProjects.sourceLanguageClassifierValue',
            'subProjects.destinationLanguageClassifierValue',
            'sourceFiles',
            'finalFiles',
            'helpFiles',
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
