<?php

namespace App\Http\Controllers\API;

use App\Enums\ProjectStatus;
use App\Enums\SubProjectStatus;
use App\Enums\VolumeUnits;
use App\Helpers\DateUtil;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectCancelRequest;
use App\Http\Requests\API\ProjectCreateRequest;
use App\Http\Requests\API\ProjectListRequest;
use App\Http\Requests\API\ProjectsExportRequest;
use App\Http\Requests\API\ProjectUpdateRequest;
use App\Http\Resources\API\ProjectResource;
use App\Http\Resources\API\ProjectSummaryResource;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Volume;
use App\Policies\ProjectPolicy;
use AuditLogClient\Services\AuditLogMessageBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use League\Csv\ByteSequence;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['price', 'deadline_at', 'created_at'])),
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

        $showOnlyPersonalProjects = filter_var($params->get('only_show_personal_projects', false), FILTER_VALIDATE_BOOLEAN);
        $showOnlyUnclaimedProjects  = $params->get('statuses', []) === [ProjectStatus::New->value];
        $this->authorize('viewAny', [Project::class, $showOnlyPersonalProjects, $showOnlyUnclaimedProjects]);

        $query = self::getBaseQuery()
            ->with([
                'typeClassifierValue.projectTypeConfig',
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

        if ($params->get('language_directions')) {
            $query = $query->hasAnyOfLanguageDirections($request->getLanguagesZippedByDirections());
        }

        if ($showOnlyPersonalProjects) {
            $query = $query->where(function (Builder $query) {
                $query
                    ->where('manager_institution_user_id', Auth::user()->institutionUserId)
                    ->orWhere('client_institution_user_id', Auth::user()->institutionUserId);
            });
        }

        $data = $query
            ->orderBy($request->validated('sort_by', 'created_at'), $request->validated('sort_order', 'desc'))
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
                'status' => filled($params->get('manager_institution_user_id'))
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

            $project->refresh();
            $project->workflow()->start();

            $this->auditLogPublisher->publishCreateObject($project);

            $project->load([
                'media',
                'managerInstitutionUser',
                'clientInstitutionUser',
                'typeClassifierValue',
                'translationDomainClassifierValue',
                'subProjects.assignments'
            ]);
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
            'typeClassifierValue.projectTypeConfig',
            'translationDomainClassifierValue',
            'subProjects',
            'subProjects.sourceLanguageClassifierValue',
            'subProjects.destinationLanguageClassifierValue',
            'subProjects.activeJobDefinition',
            'sourceFiles',
            'finalFiles',
            'helpFiles',
            'reviewFiles',
            'reviewRejections.files',
            'tags'
        ])->findOrFail($id);

        $this->authorize('view', $project);

        return new ProjectResource($project);
    }

    /**
     * Update the specified resource in storage.
     * @throws AuthorizationException
     * @throws Throwable
     */
    public function update(ProjectUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        /** @var Project $project */
        $project = $this->getBaseQuery()->find($id) ?? abort(404);

        if (filled($client = $request->validated('client_institution_user_id')) && $project->client_institution_user_id !== $client) {
            $this->authorize('changeClient', $project);
            if (count($request->validated()) > 1) {
                $this->authorize('update', $project);
            }
        } else {
            $this->authorize('update', $project);
        }

        return DB::transaction(function () use ($project, $params) {
            $this->auditLogPublisher->publishModifyObjectAfterAction(
                $project,
                function () use ($project, $params) {
                    // Collect certain keys from input params, filter null values
                    // and fill model with result from filter
                    tap(collect($params)->only([
                        'type_classifier_value_id',
                        'translation_domain_classifier_value_id',
                        'manager_institution_user_id',
                        'client_institution_user_id',
                        'reference_number',
                        'comments',
                        'deadline_at',
                        'event_start_at'
                    ])->filter()->toArray(), $project->fill(...));

                    $project->save();

                    $tagsInput = $params->get('tags');
                    if (is_array($tagsInput)) {
                        $project->tags()->detach();
                        $project->tags()->attach($tagsInput);
                    }

                    $sourceLang = $params->get('source_language_classifier_value_id', fn() => $project->subProjects->pluck('source_language_classifier_value_id')->first());
                    $destinationLangs = $params->get('destination_language_classifier_value_ids', fn() => $project->subProjects->pluck('destination_language_classifier_value_id'));
                    $reInitializeSubProjects = $project->wasChanged('type_classifier_value_id');
                    $projectHasStartedSubProjectWorkflow = $project->subProjects
                            ->filter(fn(SubProject $subProject) => $subProject->workflow()->isStarted())
                            ->count() > 0;

                    [$createdCount, $deletedCount] = $project->initSubProjects(
                        ClassifierValue::findOrFail($sourceLang),
                        ClassifierValue::findMany($destinationLangs),
                        $reInitializeSubProjects
                    );

                    if ($projectHasStartedSubProjectWorkflow && ($createdCount || $deletedCount)) {
                        abort(Response::HTTP_BAD_REQUEST, 'Updating of sub-projects not allowed in case at least one sub-project workflow started');
                    }

                    if ($project->workflow()->isStarted() && ($createdCount || $deletedCount)) {
                        $project->workflow()->restart();
                    }
                }
            );

            $project->load([
                'managerInstitutionUser',
                'clientInstitutionUser',
                'typeClassifierValue.projectTypeConfig',
                'translationDomainClassifierValue',
                'subProjects',
                'subProjects.sourceLanguageClassifierValue',
                'subProjects.destinationLanguageClassifierValue',
                'subProjects.activeJobDefinition',
                'sourceFiles',
                'finalFiles',
                'helpFiles',
                'tags',
            ]);

            return new ProjectResource($project);
        });
    }


    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/projects/{id}/cancel',
        description: 'Only projects with status `NEW` or `REGISTERED` can be cancelled. The project can be cancelled by the client or PM',
        requestBody: new OAH\RequestBody(ProjectCancelRequest::class),
        tags: ['Projects'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Project with given UUID')]
    public function cancel(ProjectCancelRequest $request): ProjectResource
    {
        return DB::transaction(function () use ($request) {
            /** @var Project $project */
            $project = self::getBaseQuery()
                ->with(['subProjects'])
                ->findOrFail($request->route('id'));

            $this->authorize('cancel', $project);

            if (!in_array($project->status, [ProjectStatus::New, ProjectStatus::Registered])) {
                abort(Response::HTTP_BAD_REQUEST, 'Only projects with status `NEW` or `REGISTERED` can be cancelled.');
            }

            $project->status = ProjectStatus::Cancelled;
            $project->fill($request->validated());
            $project->saveOrFail();

            if ($project->workflow()->isStarted()) {
                $project->workflow()->cancel($request->validated('cancellation_reason'));
            }

            return ProjectResource::make($project->refresh());
        });
    }

    /**
     * @throws InvalidArgument
     * @throws AuthorizationException
     * @throws CannotInsertRecord
     * @throws Exception
     */
    #[OA\Get(
        path: '/projects/export-csv',
        tags: ['Projects'],
        parameters: [
            new OA\QueryParameter(
                name: 'status[]',
                description: 'Filter the result set to projects which have any of the specified statuses.',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: ProjectStatus::class)
                )
            ),
            new OA\QueryParameter(
                name: 'date_from',
                description: 'Filter the result set to projects which were created after specified date',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2023-12-31')
            ),
            new OA\QueryParameter(
                name: 'date_to',
                description: 'Filter the result set to projects which were created before specified date',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2023-12-31')
            ),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'CSV file of projects based on the passed filter params',
        content: new OA\MediaType(
            mediaType: 'text/csv',
            schema: new OA\Schema(type: 'string')
        )
    )]
    public function exportCsv(ProjectsExportRequest $request)
    {
        $this->authorize('export', Project::class);

        $csvDocument = Writer::createFromString()->setDelimiter(';')
            ->setOutputBOM(ByteSequence::BOM_UTF8);

        $csvDocument->insertOne([
            'Ülesande ID',
            'Viitenumber',
            'Tellimuse tüüp',
            'Lähtekeel',
            'Sihtkeeled',
            'Tõlkekorraldaja',
            'Teostajad',
            'Staatus',
            'Tellija',
            'Valdkond',
            'Loodud',
            'Lepingupartnerite ärinimed',
            'Miinimum',
            'Teostamisele kulunud aeg (minutid)',
            'Teostamisele kulunud aeg (tunnid)',
            'Lehekülgede arv',
            'Tähemärkide arv',
            'Sõnade arv',
            'Maksumus',
            'Algusaeg',
            'Lõppaeg',
            'Täitmise kuupäev',
            'Täitmise kuu',
            'Tellija üksus',
            'Tellimuse sildid',
        ]);

        $params = collect($request->validated());

        Project::withGlobalScope('policy', ProjectPolicy::scope())->with([
            'typeClassifierValue',
            'managerInstitutionUser',
            'clientInstitutionUser',
            'translationDomainClassifierValue',
            'tags',
            'sourceLanguageClassifierValue',
            'destinationLanguageClassifierValues',
            'assignees.institutionUser',
            'catToolTmKeys',
            'volumes'
        ])->when(
            $params->get('status'),
            fn(Builder $query, $statuses) => $query->whereIn('status', $statuses)
        )->when(
            $params->get('date_from'),
            fn(Builder $query, $fromDate) => $query->whereDate('created_at', '>=', $fromDate)
        )->when(
            $params->get('date_to'),
            fn(Builder $query, $toDate) => $query->whereDate('created_at', '<=', $toDate)
        )->lazy()->each(function (Project $project) use ($csvDocument) {
            $project->assignments->each(function (Assignment $assignment) use ($csvDocument, $project) {
                $subProject = $assignment->subProject;

                // https://github.com/keeleinstituut/tv-tolkevarav/issues/819#issuecomment-2538206783
                $assignee = $assignment->assignee?->institutionUser?->getUserFullName();
                if (empty($assignee) && $subProject->status === SubProjectStatus::Completed) {
                    $assignee = $project->managerInstitutionUser?->getUserFullName();
                }

                $csvDocument->insertOne([
                    $assignment->ext_id,
                    $project->reference_number,
                    data_get($project->typeClassifierValue?->meta, 'code'),
                    $subProject?->sourceLanguageClassifierValue?->value,
                    $subProject?->destinationLanguageClassifierValue?->value,
                    $project->managerInstitutionUser?->getUserFullName(),
                    $assignee,
                    $subProject?->status?->value,
                    $project->clientInstitutionUser?->getUserFullName(),
                    $project->translationDomainClassifierValue?->name,
                    $this->getDateTimeWithTimezoneOrNull($assignment->created_at, 'd/m/Y H:i'),
                    $assignment->assignee?->company_name,
                    $assignment->volumes->filter(fn(Volume $volume) => $volume->unit_type === VolumeUnits::MinimalFee)->pluck('unit_quantity')->sum(),
                    $assignment->volumes->filter(fn(Volume $volume) => $volume->unit_type === VolumeUnits::Minutes)->pluck('unit_quantity')->sum(),
                    $assignment->volumes->filter(fn(Volume $volume) => $volume->unit_type === VolumeUnits::Hours)->pluck('unit_quantity')->sum(),
                    $assignment->volumes->filter(fn(Volume $volume) => $volume->unit_type === VolumeUnits::Pages)->pluck('unit_quantity')->sum(),
                    $assignment->volumes->filter(fn(Volume $volume) => $volume->unit_type === VolumeUnits::Characters)->pluck('unit_quantity')->sum(),
                    $assignment->volumes->filter(fn(Volume $volume) => $volume->unit_type === VolumeUnits::Words)->pluck('unit_quantity')->sum(),
                    is_null($assignment->price) ? '' : "$assignment->price €",
                    $this->getDateTimeWithTimezoneOrNull($assignment->event_start_at ?: $project->event_start_at, 'd/m/Y H:i'),
                    $this->getDateTimeWithTimezoneOrNull($assignment->deadline_at ?: $project->deadline_at, 'd/m/Y H:i'),
                    $this->getDateTimeWithTimezoneOrNull($assignment->completed_at,'d/m/Y H:i'),
                    $this->getDateTimeWithTimezoneOrNull($assignment->completed_at)?->locale('et_EE')->format('Y F'),
                    $project->clientInstitutionUser?->getDepartmentName(),
                    $project->tags?->pluck('name')->implode(', '),
                ]);
            });
        });

        $this->auditLogPublisher->publish(
            AuditLogMessageBuilder::makeUsingJWT()
                ->toExportProjectsReportEvent(
                    $params->get('date_from'),
                    $params->get('date_to'),
                    $params->get('status'),
                )
        );

        return response()->streamDownload(
            $csvDocument->output(...),
            'projects.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    private function getDateTimeWithTimezoneOrNull(Carbon $datetime = null, string $format = null): Carbon|string|null
    {
        if (empty($datetime)) {
            return null;
        }

        if (empty($format)) {
            return $datetime->timezone(DateUtil::TIMEZONE);
        }

        return $datetime->timezone(DateUtil::TIMEZONE)->format($format);
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
