<?php

namespace App\Http\Controllers\API;

use App\Exceptions\CalendarSlotConflictException;
use App\Enums\CalendarRole;
use App\Enums\CandidateStatus;
use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Enums\SubProjectStatus;
use App\Enums\VolumeUnits;
use App\Helpers\DateUtil;
use App\Http\Controllers\Controller;
use App\Jobs\ProjectDelayedCancelJob;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectCancelRequest;
use App\Http\Requests\API\ProjectDeclineCancellationRequest;
use App\Http\Requests\API\ProjectCreateRequest;
use App\Http\Requests\API\ProjectListRequest;
use App\Http\Requests\API\ProjectsExportRequest;
use App\Http\Requests\API\ProjectUpdateRequest;
use App\Http\Resources\API\ProjectResource;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\Volume;
use App\Policies\ProjectPolicy;
use App\Services\Calendar\CalendarRoleResolver;
use App\Services\Calendar\CalendarSettingsResolver;
use App\Services\Calendar\SlotMatchingService;
use App\Services\Calendar\VendorReservationService;
use AuditLogClient\Services\AuditLogMessageBuilder;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use League\Csv\ByteSequence;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectController extends Controller
{
    public function __construct(
        private readonly SlotMatchingService      $slotMatchingService,
        private readonly VendorReservationService $vendorReservation,
        private readonly CalendarRoleResolver     $calendarRoleResolver,
        private readonly CalendarSettingsResolver $calendarSettings,
        private readonly NotificationPublisher    $notificationPublisher,
        AuditLogPublisher                         $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

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
    #[OAH\PaginatedCollectionResponse(itemsRef: ProjectResource::class, description: 'Filtered projects of current institution')]
    public function index(ProjectListRequest $request): AnonymousResourceCollection
    {
        $params = collect($request->validated());

        $showOnlyPersonalProjects = filter_var($params->get('only_show_personal_projects', false), FILTER_VALIDATE_BOOLEAN);
        $showOnlyUnclaimedProjects = $params->get('statuses', []) === [ProjectStatus::New->value];
        $this->authorize('viewAny', [Project::class, $showOnlyPersonalProjects, $showOnlyUnclaimedProjects]);

        $query = self::getBaseQuery()
            ->with([
                'typeClassifierValue.projectTypeConfig',
                'tags',
                'subProjects',
                'subProjects.sourceLanguageClassifierValue',
                'subProjects.destinationLanguageClassifierValue',
                'clientInstitutionUser',
            ]);

        if ($param = $params->get('q')) {
            $query = $query->where(function ($query) use ($param) {
                $query->where('ext_id', 'ilike', "%$param%")
                    ->orWhere('reference_number', 'ilike', "%$param%");
            });
        }

        if ($param = $params->get('ext_id')) {
            $query = $query->where('ext_id', 'ilike', "%$param%");
        }

        if ($param = $params->get('statuses')) {
            $query = $query->whereIn('status', $param);
        }

        if ($param = $params->get('type_classifier_value_ids')) {
            $query = $query->whereIn('type_classifier_value_id', $param);
        }

        if ($param = $params->get('client_institution_user_ids')) {
            $query = $query->whereIn('client_institution_user_id', $param);
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

        if ($param = $params->get('deadline_at')) {
            $query = $query->whereDate('deadline_at', $param);
        }

        if ($param = $params->get('created_at')) {
            $query = $query->whereDate('created_at', $param);
        }

        if ($param = $params->get('event_start_at')) {
            $query = $query->whereDate('event_start_at', $param);
        }

        $query = $query
            ->join('cached_institution_users', 'projects.client_institution_user_id', '=', 'cached_institution_users.id')
            ->select('projects.*')
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

            case 'event_start_at':
                $query = $query->orderBy('event_start_at', $sortOrder);
                break;

            case 'status':
                $query = $query->orderBy('status', $sortOrder);
                break;

            case 'reference_number':
                $query = $query->orderBy('reference_number', $sortOrder);
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
            $institutionId = Auth::user()->institutionId;
            $institutionUserId = Auth::user()->institutionUserId;

            $typeId = $params->get('type_classifier_value_id')
                ?: ($params->get('is_calendar_project', false)
                    ? $this->calendarSettings->getDefaultCalendarProjectTypeId($institutionId)
                    : null);
            $isCalendar = ClassifierValue::isVerbalProjectType($typeId);

            $managerUserId = $params->get('manager_institution_user_id');
            if ($isCalendar && blank($managerUserId) && Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value)) {
                $managerUserId = $institutionUserId;
            }

            $project = Project::make([
                'institution_id' => $institutionId,
                'type_classifier_value_id' => $typeId,
                'translation_domain_classifier_value_id' => $params->get('translation_domain_classifier_value_id'),
                'reference_number' => $params->get('reference_number'),
                'manager_institution_user_id' => $managerUserId,
                'client_institution_user_id' => $params->get('client_institution_user_id', $institutionUserId),
                'deadline_at' => $params->get('deadline_at'),
                'comments' => $params->get('comments'),
                'event_start_at' => $params->get('event_start_at'),
                'status' => filled($managerUserId)
                    ? ProjectStatus::Registered
                    : ProjectStatus::New,
                'workflow_template_id' => Config::get('app.workflows.process_definitions.project'),
                'is_calendar_project' => $isCalendar,
                'event_end_at' => $params->get('event_end_at'),
                'service_type' => $params->get('service_type'),
                'location' => $params->get('location'),
                'meeting_link' => $params->get('meeting_link'),
                'use_external_vendor' => $params->get('use_external_vendor', false),
            ]);

            $this->authorize('create', $project);

            $project->saveOrFail();

            $tagsInput = $params->get('tags', []);
            if (filled($tagsInput)) {
                $project->tags()->attach($tagsInput);
            }

            if (filled($params->get('comment'))) {
                (new ProjectComment)->fill([
                    'project_id' => $project->id,
                    'comment' => $params->get('comment'),
                    'institution_user_id' => Auth::user()->institutionUserId,
                ])->saveOrFail();
            }

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

            $sourceLanguage = $isCalendar && blank($params->get('source_language_classifier_value_id'))
                ? ClassifierValue::where('type', ClassifierValueType::Language)->where('value', 'et-EE')->firstOrFail()
                : ClassifierValue::findOrFail($params->get('source_language_classifier_value_id'));

            $project->initSubProjects(
                $sourceLanguage,
                ClassifierValue::findMany($params->get('destination_language_classifier_value_ids'))
            );

            $project->refresh();

            $project->workflow()->start();

            $isCalendar && $this->handleCalendarProjectCreation($project, $params);

            $this->auditLogPublisher->publishCreateObject($project);

            $project->load([
                'media',
                'managerInstitutionUser',
                'clientInstitutionUser',
                'typeClassifierValue',
                'translationDomainClassifierValue',
                'subProjects.assignments',
                'projectComments.institutionUser',
                'tags',
            ]);

            return ProjectResource::make($project);
        });
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    /**
     * @throws ValidationException
     * @throws Throwable
     */
    private function handleCalendarProjectCreation(Project $project, Collection $params): void
    {
        $subProject = $project->subProjects->first();
        $assignment = $subProject->assignments->first();
        $isClient = $this->calendarRoleResolver->resolve() === CalendarRole::Client;
        $actingUserId = Auth::user()->institutionUserId;
        $prebook = VendorCalendarEntry::where('prebook_institution_user_id', $actingUserId)
            ->overlapping($project->event_start_at, $project->event_end_at)
            ->first();

        if ($project->use_external_vendor) {
            $this->buildExternalVendorsCascade($project, $assignment);
        } elseif (filled($prebook)) {
            $this->assignFromPrebook($project, $assignment, $prebook, $isClient);
        } elseif ($candidateVendorId = $params->get('candidate_vendor_id')) {
            /** @var string $candidateVendorId */
            $this->assignExplicitVendor($project, $assignment, $candidateVendorId, $actingUserId);
        } elseif ($isClient) {
            $this->assignBestAvailableVendor($project, $assignment, $isClient, $actingUserId);
        }

        $this->vendorReservation->releasePrebook($actingUserId);

        $subProject->workflow()->start();
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
            'subProjects.assignments.candidates.vendor.institutionUser',
            'subProjects.assignments.assignee.institutionUser',
            'subProjects.sourceLanguageClassifierValue',
            'subProjects.destinationLanguageClassifierValue',
            'subProjects.activeJobDefinition',
            'subProjects.assignments.candidates.vendor.institutionUser',
            'subProjects.assignments.assignee.institutionUser',
            'subProjects.assignments.jobDefinition',
            'sourceFiles',
            'finalFiles',
            'helpFiles',
            'reviewFiles',
            'reviewRejections.files',
            'tags',
            'projectComments',
        ])->findOrFail($id);

        $this->authorize('view', $project);

        return ProjectResource::make($project);
    }

    /**
     * Update the specified resource in storage.
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/projects/{id}',
        summary: 'Update an existing project',
        requestBody: new OAH\RequestBody(ProjectUpdateRequest::class),
        tags: ['Projects'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Updated project')]
    public function update(ProjectUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        /** @var Project $project */
        $project = $this->getBaseQuery()->find($id) ?? abort(404);

        // Check changeProjectManager policy when client is changed
        if (filled($client = $params->get('client_institution_user_id')) && $project->client_institution_user_id !== $client) {
            $this->authorize('changeClient', $project);
        }

        // Check changeProjectManager policy when manager is changed
        if (filled($manager = $params->get('manager_institution_user_id')) && $project->manager_institution_user_id !== $manager) {
            $this->authorize('changeProjectManager', $project);
        }

        // For all other fields check update policy
        if (count($params->except(['client_institution_user_id', 'manager_institution_user_id'])) > 0) {
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
                        'event_start_at',
                        'event_end_at',
                        'service_type',
                        'location',
                        'meeting_link',
                    ])->filter()->toArray(), $project->fill(...));

                    if ($project->is_calendar_project && blank($project->manager_institution_user_id) && Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value)) {
                        $project->manager_institution_user_id = Auth::user()->institutionUserId;
                    }

                    if ($params->has('use_external_vendor')) {
                        $project->use_external_vendor = (bool)$params->get('use_external_vendor');
                    }

                    $project->save();

                    $tagsInput = $params->get('tags', []);
                    if (filled($tagsInput)) {
                        $project->tags()->detach();
                        $project->tags()->attach($tagsInput);
                    }

                    $previousDestLangId = $project->subProjects->first()?->destination_language_classifier_value_id;
                    $newDestLangId = collect($params->get('destination_language_classifier_value_ids', []))->first() ?? $previousDestLangId;
                    $languageChanged = $newDestLangId !== $previousDestLangId;

                    $sourceLang = $params->get('source_language_classifier_value_id', fn() => $project->subProjects->pluck('source_language_classifier_value_id')->first());
                    $destinationLangs = $params->get('destination_language_classifier_value_ids', fn() => $project->subProjects->pluck('destination_language_classifier_value_id'));
                    $reInitializeSubProjects = $project->wasChanged('type_classifier_value_id');
                    $projectHasStartedSubProjectWorkflow = $project->subProjects
                            ->filter(fn(SubProject $subProject) => $subProject->workflow()->isStarted())
                            ->count() > 0;

                    $timeframeChanged = $project->wasChanged(['event_start_at', 'event_end_at']);
                    $useExternalVendorChanged = $project->wasChanged('use_external_vendor');

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

                    $calendarRelevantChange = $project->is_calendar_project && (
                            filled($params->get('candidate_vendor_id')) ||
                            $useExternalVendorChanged ||
                            $timeframeChanged ||
                            $languageChanged
                        );

                    if ($calendarRelevantChange) {
                        $this->assertCalendarUpdateAllowed($project);
                    }

                    if ($calendarRelevantChange) {
                        $project->load('subProjects.assignments');
                        $this->handleCalendarProjectUpdate($project, $params, $timeframeChanged, $languageChanged, $useExternalVendorChanged);
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
                'projectComments.institutionUser',
            ]);

            return ProjectResource::make($project);
        });
    }

    /**
     * @throws ValidationException
     */
    private function handleCalendarProjectUpdate(
        Project    $project,
        Collection $params,
        bool       $timeframeChanged,
        bool       $languageChanged,
        bool       $useExternalVendorChanged,
    ): void
    {
        $isClient = $this->calendarRoleResolver->resolve() === CalendarRole::Client;
        $assignment = $project->subProjects->first()->assignments->first();
        $calendarDataChanged = $timeframeChanged || $languageChanged;

        if ($isClient && $calendarDataChanged) {
            $this->vendorReservation->releaseAll($assignment);

            $this->assignBestAvailableVendor(
                $project,
                $assignment,
                true,
                Auth::user()->institutionUserId,
            );
            return;
        }

        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();
        $candidateVendorId = $params->get('candidate_vendor_id');
        if (filled($candidateVendorId) && $candidateVendorId !== $calendarEntry?->vendor_id) {
            $this->vendorReservation->releaseAll($assignment);
            $this->assignExplicitVendor($project, $assignment, $candidateVendorId, Auth::user()->institutionUserId);
            return;
        }

        if ($useExternalVendorChanged) {
            $this->vendorReservation->releaseAll($assignment);
            if ($project->use_external_vendor) {
                $this->buildExternalVendorsCascade($project, $assignment);
                return;
            }

            if ($isClient) {
                $this->assignBestAvailableVendor(
                    $project,
                    $assignment,
                    false,
                    Auth::user()->institutionUserId,
                );
            }

            return;
        }

        if ($calendarDataChanged) {
            $this->syncExistingVendorToNewSlot($project, $assignment);
        }
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
                abort(Response::HTTP_BAD_REQUEST, 'Ainult `NEW` või `REGISTERED` staatusega projekte saab tühistada.');
            }

            if ($project->cancellation_pending_at) {
                abort(Response::HTTP_CONFLICT, 'Tühistamine on ootel.');
            }

            $isDelayed = $project->is_calendar_project && ($request->validated('is_delayed') ?? true);

            if ($isDelayed) {
                $project->cancellation_pending_at = now();
                $project->cancellation_reason = $request->validated('cancellation_reason');
                $project->cancellation_comment = $request->validated('cancellation_comment');
                $project->saveOrFail();

                ProjectDelayedCancelJob::dispatch($project->id)
                    ->delay(now()->addSeconds(ProjectDelayedCancelJob::CANCELLATION_DELAY_SECONDS));

                return ProjectResource::make($project->refresh());
            }

            $project->status = ProjectStatus::Cancelled;
            $project->fill([
                'cancellation_reason' => $request->validated('cancellation_reason'),
                'cancellation_comment' => $request->validated('cancellation_comment'),
            ]);
            $project->saveOrFail();

            if ($project->workflow()->isStarted()) {
                $project->workflow()->cancel($request->validated('cancellation_reason'));
            }

            return ProjectResource::make($project->refresh());
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/projects/{id}/cancel-decline',
        description: 'Decline a pending cancellation of a calendar project within the grace period',
        tags: ['Projects'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Project with cancellation declined')]
    public function declineCancellation(ProjectDeclineCancellationRequest $request): ProjectResource
    {
        /** @var Project $project */
        $project = self::getBaseQuery()
            ->findOrFail($request->route('id'));

        $this->authorize('cancel', $project);

        if (!$project->cancellation_pending_at) {
            abort(Response::HTTP_BAD_REQUEST, 'Tühistamise taotlus puudub.');
        }

        $project->cancellation_pending_at = null;
        $project->cancellation_reason = null;
        $project->cancellation_comment = null;
        $project->saveOrFail();

        return ProjectResource::make($project->refresh());
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
                    $this->getDateTimeWithTimezoneOrNull($assignment->completed_at, 'd/m/Y H:i'),
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

    /**
     * @throws ValidationException
     */
    private function assignFromPrebook(Project $project, Assignment $assignment, VendorCalendarEntry $prebook, bool $isClient = false): void
    {
        $timeSlot = $this->calendarSettings->resolveTimeSlotForProject($project);
        try {
            $this->vendorReservation->reserveFromPrebook($assignment, $prebook, $timeSlot);
        } catch (CalendarSlotConflictException) {
            if (!$isClient) {
                throw ValidationException::withMessages([
                    'event_start_at' => $timeSlot->isBuffered() ?
                        'Teostaja ei ole saadaval soovitavas ajavahemikus, kuna kontakttõlkeks vajalik puhveraeg kattub teiste tellimustega.':
                        'Teostaja ei ole saadaval valitud ajavahemikul.',
                ]);
            }
        }
    }

    private function buildExternalVendorsCascade(
        Project    $project,
        Assignment $assignment
    ): void
    {
        $externals = $this->slotMatchingService->rankExternalVendorCascadeForProject($project);
        $timeSlot = $this->calendarSettings->resolveTimeSlotForProject($project);
        $entryCreated = false;

        foreach ($externals as $vendor) {
            $isAvailable = $this->slotMatchingService->hasNoConflictingEntries(
                $vendor->id, $timeSlot->bufferedStartAt, $timeSlot->bufferedEndAt,
            );

            if (!$isAvailable) {
                continue;
            }

            if (!$entryCreated) {
                try {
                    $this->vendorReservation->reserve(
                        $assignment,
                        $vendor->id,
                        $timeSlot->bufferedStartAt,
                        $timeSlot->bufferedEndAt,
                    );
                    $entryCreated = true;
                } catch (CalendarSlotConflictException) {
                    continue;
                }
            } else {
                Candidate::create([
                    'assignment_id' => $assignment->id,
                    'vendor_id' => $vendor->id,
                    'status' => CandidateStatus::New,
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function assignExplicitVendor(
        Project    $project,
        Assignment $assignment,
        string     $candidateVendorId,
        string     $actingUserId,
    ): void
    {
        $vendor = Vendor::with('institutionUser')->find($candidateVendorId);
        $timeSlot = $this->calendarSettings->resolveTimeSlotForProject($project);
        $isAvailable = $this->slotMatchingService->isVendorAvailableForSlot(
            $vendor,
            $timeSlot,
            $project->institution_id,
            $actingUserId,
        );

        if (!$isAvailable) {
            throw ValidationException::withMessages([
                'candidate_vendor_id' => $timeSlot->isBuffered() ?
                    'Teostaja ei ole saadaval soovitavas ajavahemikus, kuna kontakttõlkeks vajalik puhveraeg kattub teiste tellimustega.' :
                    'Valitud teostaja ei ole saadaval valitud ajavahemikul.',
            ]);
        }

        try {
            $this->vendorReservation->reserve(
                $assignment,
                $candidateVendorId,
                $timeSlot->bufferedStartAt,
                $timeSlot->bufferedEndAt,
            );
        } catch (CalendarSlotConflictException) {
            throw ValidationException::withMessages([
                'candidate_vendor_id' => $timeSlot->isBuffered() ?
                    'Teostaja ei ole saadaval soovitavas ajavahemikus, kuna kontakttõlkeks vajalik puhveraeg kattub teiste tellimustega.' :
                    'Valitud teostaja ei ole saadaval soovitud ajavahemikul.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assignBestAvailableVendor(
        Project    $project,
        Assignment $assignment,
        bool       $isClient,
        string     $actingUserId
    ): void
    {
        $excludeVendorIds = collect();
        $maxAttempts = 3;
        $timeSlot = $this->calendarSettings->resolveTimeSlotForProject($project);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $bestVendor = $this->slotMatchingService->pickBestInternalVendorForProject(
                $project,
                $actingUserId,
                excludeVendorIds: $excludeVendorIds,
            );

            if (blank($bestVendor)) {
                if (!$isClient) {
                    /**
                     * We should always create a project despite the lack of available vendors
                     * if the acting user is a client. For TPM, we show a validation error
                     */
                    throw ValidationException::withMessages([
                        'event_start_at' => 'Soovitud ajavahemikul ja keelesuunal ei ole ühtegi teostajat saadaval.',
                    ]);
                }

                $this->publishVendorWasNotAssignedAutomaticallyEmailNotification($project);
                return;
            }

            try {
                $this->vendorReservation->reserve(
                    $assignment,
                    $bestVendor->id,
                    $timeSlot->bufferedStartAt,
                    $timeSlot->bufferedEndAt,
                );
                return;
            } catch (CalendarSlotConflictException) {
                $excludeVendorIds->push($bestVendor->id);
                continue;
            }
        }

        $this->publishVendorWasNotAssignedAutomaticallyEmailNotification($project);

        if (!$isClient) {
            throw ValidationException::withMessages([
                'event_start_at' => 'Soovitud ajavahemikul ja keelesuunal ei ole ühtegi teostajat saadaval.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function syncExistingVendorToNewSlot(
        Project    $project,
        Assignment $assignment,
    ): void
    {
        $currentCandidate = Candidate::with('vendor.institutionUser')
            ->where('assignment_id', $assignment->id)
            ->whereNot('status', [CandidateStatus::Declined, CandidateStatus::Rejected])
            ->orderBy('position')
            ->first();

        if (blank($currentCandidate)) {
            VendorCalendarEntry::where('assignment_id', $assignment->id)->delete();
            return;
        }

        $timeSlot = $this->calendarSettings->resolveTimeSlotForProject($project);

        $isAvailable = $this->slotMatchingService->isVendorAvailableForSlot(
            $currentCandidate->vendor,
            $timeSlot,
            $project->institution_id,
            excludeAssignmentId: $assignment->id,
        );

        if (!$isAvailable) {
            throw ValidationException::withMessages([
                'event_start_at' => 'Määratud teostaja ei ole saadaval uuendatud ajavahemikul.',
            ]);
        }

        try {
            $assignment->calendarEntry?->update([
                'start_at' => $timeSlot->bufferedStartAt,
                'end_at' => $timeSlot->bufferedEndAt,
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'event_start_at' => 'Määratud teostaja ei ole saadaval uuendatud ajavahemikul.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertCalendarUpdateAllowed(Project $project): void
    {
        $isClient = $this->calendarRoleResolver->resolve() === CalendarRole::Client;

        if ($isClient) {
            $hasAcceptedCandidate = $project->subProjects->first()?->assignments
                ->first()?->assignee()->exists() ?? false;

            if ($hasAcceptedCandidate) {
                throw ValidationException::withMessages([
                    'event_start_at' => 'Projekti ei saa muuta pärast seda, kui teostaja on töö vastu võtnud.',
                ]);
            }
        } elseif ($project->status === ProjectStatus::Accepted) {
            throw ValidationException::withMessages([
                'event_start_at' => 'Lõpetatud projekti ei saa muuta.',
            ]);
        }
    }

    public function publishVendorWasNotAssignedAutomaticallyEmailNotification(Project $project): void
    {
        $receiver = $project->managerInstitutionUser;
        $receiverEmail = $receiver?->email;
        $receiverName = $receiver?->getUserFullName();

        if (empty($receiverEmail)) {
            $receiverEmail = $receiver?->email ?: $project->institution?->email;
            $receiverName = $receiver?->getUserFullName() ?: $project->institution?->name;
        }

        if (filled($receiverEmail)) {
            DB::afterCommit(function () use ($project, $receiverEmail, $receiverName) {
                $this->notificationPublisher->publishEmailNotification(
                    EmailNotificationMessage::make([
                        'notification_type' => NotificationType::VendorWasNotAssignedAutomatically,
                        'receiver_email' => $receiverEmail,
                        'receiver_name' => $receiverName,
                        'variables' => [
                            'project' => $project->only(['ext_id']),
                        ]
                    ]),
                    $project->institution_id
                );
            });
        }
    }


    private function getDateTimeWithTimezoneOrNull(?Carbon $datetime = null, ?string $format = null): Carbon|string|null
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
