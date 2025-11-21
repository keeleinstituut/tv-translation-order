<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use App\Enums\AssignmentStatus;
use App\Enums\CandidateStatus;
use App\Enums\PrivilegeKey;
use App\Enums\TaskType;
use App\Helpers\SubProjectTaskMarkedAsDoneEmailNotificationMessageComposer;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\WorkflowHistoryTaskListRequest;
use App\Http\Requests\API\WorkflowTaskListRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskResource2;
use App\Jobs\NotifyAssignmentCandidatesAboutReviewRejection;
use App\Jobs\Workflows\TrackProjectStatus;
use App\Jobs\Workflows\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Media;
use App\Models\Project;
use App\Models\ProjectReviewRejection;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Policies\InstitutionUserPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SubProjectPolicy;
use App\Policies\VendorPolicy;
use App\Services\TranslationMemories\TvTranslationMemoryApiClient;
use App\Services\Workflows\ProjectWorkflowProcessInstance;
use App\Services\Workflows\WorkflowService;
use AuditLogClient\Services\AuditLogMessageBuilder;
use AuditLogClient\Services\AuditLogPublisher;
use BadMethodCallException;
use DB;
use Gate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use NotificationClient\Services\NotificationPublisher;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use App\Models\CamundaTask;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Builder;


class WorkflowController extends Controller
{

    public function __construct(private readonly TvTranslationMemoryApiClient $tmServiceApiClient, private readonly NotificationPublisher $notificationPublisher, AuditLogPublisher $auditLogPublisher)
    {
        parent::__construct($auditLogPublisher);
    }

    public function getTasks2(WorkflowTaskListRequest $request)
    {
        $params = collect($request->validated());
        $perPage = intval($request->get('per_page'));
        $pagination = new PaginationBuilder(5000);

        $bodyParams = collect($this->buildAdditionalParams($params));

        $tasks = WorkflowService::getTasks($bodyParams, $pagination->getPaginationParams());
        $count = WorkflowService::getTasksCount($bodyParams)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);
        $data = $this->mapWithVariables($tasks, $variableInstances);

        return DB::transaction(function () use ($data, $params) {
            $queryId = $this->populateToDatabase($data);

            $query = CamundaTask::where('query_id', $queryId)
                ->with([
                    'assignment',
                    'assignment.subProject',
                    'assignment.subProject.sourceLanguageClassifierValue',
                    'assignment.subProject.destinationLanguageClassifierValue',
                    'assignment.subProject.project',
                    'assignment.subProject.project.tags',
                    'assignment.subProject.project.clientInstitutionUser',
                    'assignment.subProject.project.typeClassifierValue',
                    'project',
                    'project.tags',
                    'project.typeClassifierValue',
                    'project.clientInstitutionUser',
                    'project.subProjects.sourceLanguageClassifierValue',
                    'project.subProjects.destinationLanguageClassifierValue',
                ]);

            $query = $query
                ->join('projects', 'projects.id', '=', 'var_project_id')
                ->join('cached_institution_users', 'projects.client_institution_user_id', '=', 'cached_institution_users.id')
                ->select('camunda_tasks.*')
                ->selectRaw("concat(cached_institution_users.user->>'forename', ' ', cached_institution_users.user->>'surname') as project_client_institution_user_name"); // For ordering by client's name

            if ($param = $params->get('type_classifier_value_id')) {
                $query = $query->where('projects.type_classifier_value_id', $param);
            }

            if ($param = $params->get('tag_ids')) {
                $query = $query->whereHas('project.tags', function (Builder $builder) use ($param) {
                    $builder->whereIn('tags.id', $param);
                });
            }

            $sortBy = $params->get('sort_by');
            $sortOrder = $params->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'created_at':
                    $query = $query->orderBy('camunda_tasks.task_created', $sortOrder);
                    break;

                case 'project.ext_id':
                    $query = $query->orderBy('projects.ext_id', $sortOrder);
                    break;

                case 'project.reference_number':
                    $query = $query->orderBy('projects.reference_number', $sortOrder);
                    break;

                case 'project.price':
                    $query = $query->orderBy('projects.price', $sortOrder);
                    break;

                case 'project.deadline_at':
                    $query = $query->orderBy('projects.deadline_at', $sortOrder);
                    break;

                case 'project.event_start_at':
                    $query = $query->orderBy('projects.event_start_at', $sortOrder);
                    break;

                case 'project.clientInstitutionUser.name':
                    $query = $query->orderBy('project_client_institution_user_name', $sortOrder);
                    break;

                default:
                    $query = $query->orderBy('deadline_at', $sortOrder);
                    break;
            }

            $result = $query->paginate();

            $this->removeFromDatabase($queryId);

            return TaskResource2::collection($result);
        });
    }

    public function getHistoryTasks2(WorkflowHistoryTaskListRequest $request): AnonymousResourceCollection
    {

        $params = collect($request->validated());
        $perPage = intval($request->get('per_page'));
        $pagination = new PaginationBuilder(5000);

        $bodyParams = collect([
            ...$this->buildAdditionalParams($params, true),
            'finished' => true,
        ]);

        $tasks = WorkflowService::getHistoryTask($bodyParams, $pagination->getPaginationParams());
        $count = WorkflowService::getHistoryTaskCount($bodyParams)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks, true);
        $data = $this->mapWithVariables($tasks, $variableInstances);

        return DB::transaction(function () use ($data, $params) {
            $queryId = $this->populateToDatabase($data, true);

            $query = CamundaTask::where('query_id', $queryId)
                ->with([
                    'assignment',
                    'assignment.subProject',
                    'assignment.subProject.sourceLanguageClassifierValue',
                    'assignment.subProject.destinationLanguageClassifierValue',
                    'assignment.subProject.project',
                    'assignment.subProject.project.tags',
                    'assignment.subProject.project.clientInstitutionUser',
                    'assignment.subProject.project.typeClassifierValue',
                    'project',
                    'project.tags',
                    'project.typeClassifierValue',
                    'project.clientInstitutionUser',
                    'project.subProjects.sourceLanguageClassifierValue',
                    'project.subProjects.destinationLanguageClassifierValue',
                ]);

            $query = $query
                ->join('projects', 'projects.id', '=', 'var_project_id')
                ->join('cached_institution_users', 'projects.client_institution_user_id', '=', 'cached_institution_users.id')
                ->select('camunda_tasks.*')
                ->selectRaw("concat(cached_institution_users.user->>'forename', ' ', cached_institution_users.user->>'surname') as project_client_institution_user_name"); // For ordering by client's name

            if ($param = $params->get('type_classifier_value_id')) {
                $query = $query->where('projects.type_classifier_value_id', $param);
            }

            if ($param = $params->get('tag_ids')) {
                $query = $query->whereHas('project.tags', function (Builder $builder) use ($param) {
                    $builder->whereIn('tags.id', $param);
                });
            }

            // Works for tasks that have direct assignment, but doesn't for tasks that have only
            // project reference without assignment
            // if ($param = $params->get('lang_pair')) {
            //     $query->whereHas('assignment.subProject', function (Builder $builder) use ($param) {
            //         $builder->where(function ($builder) use ($param) {
            //             collect($param)->each(function ($pair) use ($builder) {
            //                 $builder->orWhere('source_language_classifier_value_id', $pair['src'])
            //                     ->where('destination_language_classifier_value_id', $pair['dst']);
            //             });
            //         });
            //     });
            // }

            $sortBy = $params->get('sort_by');
            $sortOrder = $params->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'created_at':
                    $query = $query->orderBy('camunda_tasks.task_created', $sortOrder);
                    break;

                case 'project.ext_id':
                    $query = $query->orderBy('projects.ext_id', $sortOrder);
                    break;

                case 'project.reference_number':
                    $query = $query->orderBy('projects.reference_number', $sortOrder);
                    break;

                case 'project.price':
                    $query = $query->orderBy('projects.price', $sortOrder);
                    break;

                case 'project.deadline_at':
                    $query = $query->orderBy('projects.deadline_at', $sortOrder);
                    break;

                case 'project.event_start_at':
                    $query = $query->orderBy('projects.event_start_at', $sortOrder);
                    break;

                case 'project.clientInstitutionUser.name':
                    $query = $query->orderBy('project_client_institution_user_name', $sortOrder);
                    break;

                default:
                    $query = $query->orderBy('deadline_at', $sortOrder);
                    break;
            }

            $result = $query->paginate();

            $this->removeFromDatabase($queryId);

            return TaskResource2::collection($result);
        });
    }

    // Saves task info temporarily to database for easier integration with existing data
    // for leveraging database functions for sorting and filtering based on fields in other existing tables.
    public function populateToDatabase($data, $forHistoricTasks = false)
    {
        $queryId = (string) Uuid::uuid4();

        $entities = collect($data)->map(function ($taskData) use ($queryId, $forHistoricTasks) {
            $row = [];
            $row['id'] = (string) Uuid::uuid4();
            $row['query_id'] = $queryId; 
            $row['task_id'] = data_get($taskData, 'task.id');
            $row['task_name'] = data_get($taskData, 'task.name');
            $row['task_created'] = data_get($taskData, $forHistoricTasks ? 'task.startTime' : 'task.created');
            $row['task_execution_id'] = data_get($taskData, 'task.executionId');
            $row['task_process_definition_id'] = data_get($taskData, 'task.processDefinitionId');
            $row['task_process_instance_id'] = data_get($taskData, 'task.processInstanceId');
            $row['task_task_definition_key'] = data_get($taskData, 'task.taskDefinitionKey');

            $row['var_project_id'] = data_get($taskData, 'variables.project_id');
            $row['var_sub_project_id'] = data_get($taskData, 'variables.sub_project_id');
            $row['var_institution_id'] = data_get($taskData, 'variables.institution_id');
            $row['var_assignment_id'] = data_get($taskData, 'variables.assignment_id');
            $row['var_type_classifier_value_id'] = data_get($taskData, 'variables.type_classifier_value_id');
            $row['var_deadline_at'] = data_get($taskData, 'variables.deadline_at');
            $row['var_task_type'] = data_get($taskData, 'variables.task_type');
            return $row;
        });

        // For some reason tasks from camunda are missing project_id when assignment_id is set.
        // Project_id is directly derivable through one-to-one relations between assignment -> subProject -> project.
        $assignments = collect(Assignment::getModel()->with('subProject.project')->whereIn('id', $entities->pluck('var_assignment_id'))->get());
        $entities = $entities->map(function ($entity) use ($assignments) {;
            $assignment = $assignments->firstWhere('id', $entity['var_assignment_id']);
            if ($assignment) {
                $entity['var_project_id'] = $assignment->subProject->project->id;
                $entity['var_sub_project_id'] = $assignment->subProject->id;
            }
            return $entity;
        });

        CamundaTask::insert($entities->toArray());
        return $queryId;
    }

    private function removeFromDatabase($queryId)
    {
        CamundaTask::where('query_id', $queryId)->delete();
    }

    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/workflow/tasks',
        tags: ['Workflow'],
        parameters: [
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['deadline_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
            new OA\QueryParameter(name: 'type_classifier_value_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'assigned_to_me', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\QueryParameter(name: 'is_candidate', description: 'Returns tasks where active user/passed institution_user_id is a candidate. Note: use it together with `assigned_to_me` = 0', schema: new OA\Schema(type: 'boolean')),
            new OA\QueryParameter(name: 'project_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'task_type', schema: new OA\Schema(type: 'string', format: 'enum', enum: TaskType::class)),
            new OA\QueryParameter(name: 'institution_user_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(
                name: 'lang_pair[]',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'src', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'dst', type: 'string', format: 'uuid'),
                        ]
                    ),
                    nullable: true
                )
            ),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\PaginatedCollectionResponse(itemsRef: TaskResource::class, description: 'Filtered tasks of current institution')]
    public function getTasks(WorkflowTaskListRequest $request): AnonymousResourceCollection
    {
        $requestParams = collect($request->validated());
        $perPage = intval($request->get('per_page'));
        $pagination = new PaginationBuilder($perPage);

        $bodyParams = collect($this->buildAdditionalParams($requestParams));

        $tasks = WorkflowService::getTasks($bodyParams, $pagination->getPaginationParams());
        $count = WorkflowService::getTasksCount($bodyParams)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithAssignmentExtraInfo($data);
        $data = $this->mapWithProjectExtraInfo($data);

        return TaskResource::collection($pagination->toPaginator($data, $count));
    }

    #[OA\Get(
        path: '/workflow/tasks/{id}',
        summary: 'Get task',
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function getTask(string $id): JsonResource
    {
        $params = collect([
            ...$this->buildAdditionalParams(collect([
                'skip_assigned_param' => true,
            ])),
            'taskId' => $id,
        ]);

        $tasks = WorkflowService::getTasks($params);

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $tasks = $this->mapWithVariables($tasks, $variableInstances);
        $tasks = $this->mapWithAssignmentExtraInfo($tasks, [
            'volumes',
            'subProject.project.clientInstitutionUser',
            'subProject.project.managerInstitutionUser',
            'subProject.project.helpFiles',
            'subProject.project.sourceFiles',
            'subProject.project.finalFiles',
            'subProject.sourceFiles',
            'subProject.finalFiles.assignment.jobDefinition',
            'subProject.catToolTmKeys',
            'catToolJobs'
        ]);
        $tasks = $this->mapWithProjectExtraInfo($tasks);

        $task = $tasks->first();
        $task = $this->mapWithTmKeysInfo($task);

        return TaskResource::make($task);
    }

    #[OA\Get(
        path: '/workflow/history/tasks',
        tags: ['Workflow'],
        parameters: [
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['deadline_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
            new OA\QueryParameter(name: 'type_classifier_value_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'project_id', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(
                name: 'lang_pair[]',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'src', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'dst', type: 'string', format: 'uuid'),
                        ]
                    ),
                    nullable: true
                )
            ),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\PaginatedCollectionResponse(itemsRef: TaskResource::class, description: 'Filtered tasks of current institution')]
    public function getHistoryTasks(WorkflowHistoryTaskListRequest $request): AnonymousResourceCollection
    {
        $requestParams = collect($request->validated());
        $perPage = intval($request->get('per_page'));
        $pagination = new PaginationBuilder($perPage);

        $bodyParams = collect([
            ...$this->buildAdditionalParams($requestParams, true),
            'finished' => true,
        ]);

        $tasks = WorkflowService::getHistoryTask($bodyParams, $pagination->getPaginationParams());
        $count = WorkflowService::getHistoryTaskCount($bodyParams)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks, true);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithAssignmentExtraInfo($data);
        $data = $this->mapWithProjectExtraInfo($data);

        return TaskResource::collection($pagination->toPaginator($data, $count));
    }

    #[OA\Get(
        path: '/workflow/history/tasks/{id}',
        summary: 'Get historic task',
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function getHistoryTask(string $id): JsonResource
    {
        $params = collect([
            ...$this->buildAdditionalParams(collect([
                'skip_assigned_param' => true,
            ]), true),
            'finished' => true,
            'taskId' => $id,
        ]);

        $tasks = WorkflowService::getHistoryTask($params);

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks, true);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithAssignmentExtraInfo($data, [
            'volumes',
            'subProject.project.clientInstitutionUser',
            'subProject.project.managerInstitutionUser',
            'subProject.sourceFiles',
            'subProject.finalFiles.assignment.jobDefinition',
            'subProject.catToolTmKeys',
            'catToolJobs'
        ]);
        $data = $this->mapWithProjectExtraInfo($data);

        $task = $data->first();
        $task = $this->mapWithTmKeysInfo($task);
        return TaskResource::make($task);
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/workflow/tasks/{id}/accept',
        description: 'Note: available only for tasks without assignee && tasks with type === `DEFAULT` as only these tasks will be done by vendors who should accept the task.',
        summary: 'Assign user to the task',
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function acceptTask(Request $request): TaskResource
    {
        $taskId = $request->route('id');
        $taskData = $this->getTaskDataOrFail($taskId);

        if (data_get($taskData, 'task.assignee')) {
            abort(Response::HTTP_BAD_REQUEST, 'The task already has assignee');
        }

        if (empty($taskType = TaskType::tryFrom(data_get($taskData, 'variables.task_type')))) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unknown task type');
        }

        if ($taskType !== TaskType::Default) {
            abort(Response::HTTP_BAD_REQUEST, 'Accepting of the tasks available only for vendor tasks');
        }

        $activeVendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', Auth::user()->institutionUserId)
            ->first();

        if (empty($activeVendor)) {
            abort(Response::HTTP_BAD_REQUEST, 'The active user is not a vendor');
        }

        $candidatesVendorIds = collect(WorkflowService::getIdentityLinks($taskId, 'candidate'))
            ->pluck('userId');

        if (!$candidatesVendorIds->contains($activeVendor->institution_user_id)) {
            abort(Response::HTTP_BAD_REQUEST, 'The vendor is not a candidate for the task');
        }

        /** @var Assignment $assignment */
        if (empty($assignment = $taskData['assignment'])) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Missed assignment for the vendor task');
        }

        DB::transaction(function () use ($taskId, $assignment, $activeVendor) {
            $assignment->assigned_vendor_id = $activeVendor->id;
            $assignment->saveOrFail();

            WorkflowService::setAssignee($taskId, $activeVendor->institution_user_id);
        });

        TrackSubProjectStatus::dispatchSync($assignment->subProject);
        $assignment->load('subProject');

        $this->auditLogPublisher->publish(
            AuditLogMessageBuilder::makeUsingJWT()
                ->toAcceptTaskEvent($assignment->id, $assignment->ext_id)
        );

        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/workflow/tasks/{id}/complete',
        description: 'Note: Request body Schema contains list of possible request bodies with description in what case they should be sent.',
        summary: 'Complete the task',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    oneOf: [
                        new OA\Schema(
                            description: 'task_type === `CLIENT_REVIEW` && accepted === true',
                            properties: [
                                new OA\Property(property: 'accepted', type: 'boolean'),
                                new OA\Property(property: 'final_file_id', type: 'array', items: new OA\Items(type: 'integer')),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(
                            description: 'task_type === `CLIENT_REVIEW` && accepted === false',
                            properties: [
                                new OA\Property(property: 'accepted', type: 'boolean'),
                                new OA\Property(property: 'sub_project_id', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'review_file', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), nullable: true),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(
                            description: 'task_type === `DEFAULT` || task_type === `CORRECTING`',
                            properties: []
                        ),
                        new OA\Schema(
                            description: 'task_type === `REVIEW`',
                            properties: [
                                new OA\Property(property: 'accepted', type: 'boolean')
                            ],
                            type: 'object'
                        ),
                    ]),
            )
        ),
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function completeTask(Request $request): TaskResource
    {
        $taskData = $this->getTaskDataOrFail($request->route('id'));
        $taskType = TaskType::tryFrom(data_get($taskData, 'variables.task_type'));

        return match ($taskType) {
            TaskType::Default => $this->completeDefaultTask($taskData),
            TaskType::Correcting => $this->completeCorrectingTask($taskData),
            TaskType::Review => $this->completeReviewTask($request, $taskData),
            TaskType::ClientReview => $this->completeProjectReviewTask($request, $taskData),
            default => throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unexpected task type')
        };
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    private function completeDefaultTask(array $taskData): TaskResource
    {
        $taskId = data_get($taskData, 'task.id');
        $taskType = TaskType::tryFrom(data_get($taskData, 'variables.task_type'));
        if ($taskType !== TaskType::Default) {
            throw new InvalidArgumentException('Task with incorrect type passed');
        }

        $vendor = $this->getActiveVendor();
        $this->authorizeVendorTaskCompletion($vendor, $taskData);

        /** @var Assignment $assignment */
        if (empty($assignment = $taskData['assignment'])) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'The task metadata is missing');
        }

        DB::transaction(function () use ($taskId, $assignment, $vendor) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();

            if (filled($vendor)) {
                /** @var Candidate $candidate */
                $candidate = $assignment->candidates()->where('vendor_id', $vendor->id)
                    ->first();

                if (filled($candidate)) {
                    $candidate->status = CandidateStatus::Done;
                    $candidate->saveOrFail();
                }
            }

            WorkflowService::completeTask($taskId);
            $assigneeInstitutionUser = $assignment->assignee?->institutionUser;
            if (filled($assigneeInstitutionUser) && filled($message = SubProjectTaskMarkedAsDoneEmailNotificationMessageComposer::compose($assignment, $assigneeInstitutionUser))) {
                $this->notificationPublisher->publishEmailNotification($message);
            }

            $projectManager = $assignment->subProject?->project?->managerInstitutionUser;
            if (filled($projectManager) && filled($message = SubProjectTaskMarkedAsDoneEmailNotificationMessageComposer::compose($assignment, $projectManager))) {
                $this->notificationPublisher->publishEmailNotification($message);
            }

            $this->auditLogPublisher->publish(
                AuditLogMessageBuilder::makeUsingJWT()
                    ->toCompleteAssignmentEvent(
                        $assignment->id,
                        $assignment->ext_id,
                    )
            );
        });

        TrackSubProjectStatus::dispatchSync($assignment->subProject);
        $assignment->load('subProject');

        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    private function completeCorrectingTask(array $taskData): TaskResource
    {
        $taskId = data_get($taskData, 'task.id');
        $taskType = TaskType::tryFrom(data_get($taskData, 'variables.task_type'));
        if ($taskType !== TaskType::Correcting) {
            throw new InvalidArgumentException('Task with incorrect type passed');
        }

        $this->authorizeProjectManagerTaskCompletion($taskData);

        if (empty($project = $this->getTaskProject($taskData))) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'The task metadata is missing');
        }

        WorkflowService::completeTask($taskId);
        TrackProjectStatus::dispatchSync($project);

        return TaskResource::make(
            $this->mapWithProjectExtraInfo([$taskData], [
                'finalFiles',
                'reviewFiles',
                'reviewRejections.files',
                'helpFiles'
            ])->first()
        );
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    private function completeReviewTask(Request $request, array $taskData): TaskResource
    {
        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
            'final_file_id' => ['required_if:accepted,1', 'array'],
            'final_file_id.*' => [
                'required',
                'integer',
                Rule::exists(Media::class, 'id'),
            ],
        ]);

        if (data_get($taskData, 'variables.task_type', TaskType::Default->value) !== TaskType::Review->value) {
            abort(Response::HTTP_BAD_REQUEST, 'The task type is not review');
        }

        /** @var Assignment $assignment */
        if (empty($assignment = $taskData['assignment'])) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Assignment not found for the task');
        }

        $this->authorizeProjectManagerTaskCompletion($taskData);

        DB::transaction(function () use ($assignment, $validated, $taskData) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();

            if ($validated['accepted']) {
                $assignment->subProject->syncFinalFilesWithProject($validated['final_file_id']);
            }

            WorkflowService::completeReviewTask(data_get($taskData, 'task.id'), $validated['accepted']);

            if ($validated['accepted']) {
                $this->auditLogPublisher->publish(
                    AuditLogMessageBuilder::makeUsingJWT()
                        ->toApproveAssignmentResultEvent(
                            $assignment->id,
                            $assignment->ext_id,
                        )
                );
            } else {
                $this->auditLogPublisher->publish(
                    AuditLogMessageBuilder::makeUsingJWT()
                        ->toRejectAssignmentResultEvent(
                            $assignment->id,
                            $assignment->ext_id,
                        )
                );
            }
        });

        TrackSubProjectStatus::dispatchSync($assignment->subProject);

        if (!$validated['accepted']) {
            NotifyAssignmentCandidatesAboutReviewRejection::dispatch($assignment->subProject);
        }

        $assignment->load('subProject');

        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    private function completeProjectReviewTask(Request $request, array $taskData): TaskResource
    {
        if (data_get($taskData, 'variables.task_type') !== TaskType::ClientReview->value) {
            abort(Response::HTTP_BAD_REQUEST, 'The task type is not client review');
        }

        if (empty($project = $this->getTaskProject($taskData))) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Could not determine project for the task');
        }

        $this->authorize('review', $project);

        $validated = collect($request->validate([
            'accepted' => ['required', 'boolean'],
            'sub_project_id' => ['required_if:accepted,0', 'array', 'min:1'],
            'sub_project_id.*' => ['uuid', function ($attribute, $value, $fail) use ($project) {
                $exists = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                    ->where('project_id', $project->id)
                    ->where('id', $value)
                    ->exists();

                if (!$exists) {
                    $fail('Subproject with such ID does not exist');
                }

            }],
            'description' => ['required_if:accepted,0', 'string', 'max:500'],
            'review_file' => ['sometimes', 'array'],
            'review_file.*' => ['file']
        ]));

        DB::transaction(function () use ($project, $taskData, $validated) {
            if (!$isAccepted = $validated->get('accepted')) {
                $projectReviewRejection = new ProjectReviewRejection();
                $projectReviewRejection->fill([
                    'sub_project_ids' => $validated->get('sub_project_id'),
                    'institution_user_id' => Auth::user()->institutionUserId,
                    'project_id' => $project->id,
                    'description' => $validated->get('description')
                ])->saveOrFail();

                collect($validated->get('review_file', []))
                    ->each(function (UploadedFile $file) use ($project, $projectReviewRejection) {
                        $project->addMedia($file)->toMediaCollection($projectReviewRejection->file_collection);
                    });
            }

            WorkflowService::completeProjectReviewTask(data_get($taskData, 'task.id'), $isAccepted);

            if ($isAccepted) {
                $this->auditLogPublisher->publish(
                    AuditLogMessageBuilder::makeUsingJWT()
                        ->toApproveProjectEvent(
                            $project->id,
                            $project->ext_id,
                        )
                );
            } else {
                $this->auditLogPublisher->publish(
                    AuditLogMessageBuilder::makeUsingJWT()
                        ->toRejectProjectEvent(
                            $project->id,
                            $project->ext_id,
                        )
                );
            }
        });


        TrackProjectStatus::dispatchSync($project);

        $taskData = $this->mapWithProjectExtraInfo([$taskData], [
            'finalFiles',
            'reviewFiles',
            'reviewRejections.files',
            'helpFiles'
        ])->first();
        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     */
    private function getTaskDataOrFail(string $id): array
    {
        if (empty($task = WorkflowService::getTask($id))) {
            abort(Response::HTTP_NOT_FOUND, 'Task not found');
        }

        $variableInstances = $this->fetchVariableInstancesForTasks([$task]);
        $data = $this->mapWithVariables([$task], $variableInstances);
        if (empty($this->mapWithAssignmentExtraInfo($data)[0])) {
            abort(Response::HTTP_NOT_FOUND, 'Task data not found');
        }

        return $this->mapWithAssignmentExtraInfo($data)[0];
    }

    private function buildAdditionalParams(Collection $requestParams, $forHistoricTasks = false): Collection
    {
        $processVariablesFilter = collect();
        $sortingParams = collect();

        if ($param = $requestParams->get('lang_pair')) {
            $langPair = collect($param)->first();
            $processVariablesFilter->push([
                "name" => 'source_language_classifier_value_id',
                "value" => $langPair['src'],
                "operator" => "eq",
            ]);
            $processVariablesFilter->push([
                'name' => 'destination_language_classifier_value_id',
                'value' => "%{$langPair['dst']}%",
                'operator' => 'like',
            ]);
        }

        // if ($param = $requestParams->get('type_classifier_value_id')) {
        //     $typeClassifierValueId = collect($param)->first();
        //     $processVariablesFilter->push([
        //         'name' => 'type_classifier_value_id',
        //         'value' => $typeClassifierValueId,
        //         'operator' => 'eq',
        //     ]);
        // }

        if ($param = $requestParams->get('task_type')) {
            $processVariablesFilter->push([
                'name' => 'task_type',
                'value' => $param,
                'operator' => 'eq',
            ]);
        }

        // if ($sortBy = $requestParams->get('sort_by')) {
        //     $sortOrder = $requestParams->get('sort_order', 'asc');
        //     $sortingParams->push([
        //         'sortBy' => 'processVariable',
        //         'sortOrder' => $sortOrder,
        //         'parameters' => [
        //             'variable' => $sortBy,
        //             'type' => 'string',
        //         ]
        //     ]);
        // }

        $processVariablesFilter->push([
            "name" => 'institution_id',
            "value" => Auth::user()->institutionId,
            "operator" => "eq",
        ]);

        $params = collect([
            'processVariables' => $processVariablesFilter->toArray(),
            'sorting' => $sortingParams->toArray(),
        ]);

        if ($projectId = $requestParams->get('project_id')) {
            $project = Project::withGlobalScope('policy', ProjectPolicy::scope())->findOrFail($projectId);
            $params->put('processInstanceBusinessKey', $project->workflow()->getBusinessKey());
        } else {
            $params->put('processInstanceBusinessKeyLike', ProjectWorkflowProcessInstance::BUSINESS_KEY_PREFIX . '%');
        }

        $assigned = $requestParams->get('assigned_to_me', true);
        $currentUserId = Auth::user()->institutionUserId;
        $institutionUserId = $requestParams->get('institution_user_id', $currentUserId);
        $isOtherInstitutionUser = $institutionUserId != $currentUserId;
        $institutionUser = InstitutionUser::withGlobalScope('policy', InstitutionUserPolicy::scope())->find($institutionUserId);

        if ($isOtherInstitutionUser) {
            $assigned = true;
            $this->authorize('viewActiveTasks', $institutionUser);
        }

        if (!$requestParams->get('skip_assigned_param')) {
            if ($assigned) {
                $params['assigned'] = true;
                // Use 'empty' as fallback since leaving fallback as null will be treated as all assignees
                $assigneeKey = $forHistoricTasks ? 'taskAssignee' : 'assignee';
                $params[$assigneeKey] = $institutionUser->id ?? '--empty--';
            } else {
                $params['unassigned'] = true;
            }
        }

        if ($requestParams->get('is_candidate', false)) {
            $params['candidateUser'] = $institutionUserId ?: '--empty--';
        }

        return $params;
    }

    private function fetchVariableInstancesForTasks(array $tasks, $history = false)
    {
        $executionIds = collect($tasks)->pluck('executionId')->implode(',');
        $params = [
            'executionIdIn' => $executionIds,
        ];
        if ($history) {
            return WorkflowService::getHistoryVariableInstance($params);
        } else {
            return WorkflowService::getVariableInstance($params);
        }
    }

    private function mapWithVariables($tasks, $variableInstances): Collection
    {
        $vars = collect($variableInstances);

        return collect($tasks)->map(function ($task) use ($vars) {
            return [
                'task' => $task,
                'variables' => $vars
                    ->filter(function ($variableInstance) use ($task) {
                        return $variableInstance['executionId'] == $task['executionId'];
                    })
                    ->mapWithKeys(function ($variableInstance) {
                        return [$variableInstance['name'] => $variableInstance['value']];
                    }),
            ];
        });
    }

    private function mapWithAssignmentExtraInfo($tasks, array $assignmentAdditionalRelations = []): Collection
    {
        $relations = collect([
            'subProject.project.typeClassifierValue',
            'subProject.sourceLanguageClassifierValue',
            'subProject.destinationLanguageClassifierValue',
            'subProject.project.tags',
            'subProject.project.clientInstitutionUser',
        ])->merge($assignmentAdditionalRelations)->unique();

        $assignmentIds = collect($tasks)->map(fn($task) => data_get($task, 'variables.assignment_id'));
        $assignments = Assignment::query()
            ->whereIn('id', $assignmentIds)
            ->with($relations->toArray())->get();

        return collect($tasks)->map(function ($task) use ($assignments) {
            if (filled($assignmentId = data_get($task, 'variables.assignment_id'))) {
                return [
                    ...$task,
                    'assignment' => $assignments->firstWhere('id', $assignmentId)
                ];
            }

            return $task;
        });
    }

    /**
     * The function is needed to add extra project info for the `CORRECTING` and `CLIENT_REVIEW` tasks.
     *
     * @param $tasks
     * @param array $projectAdditionalRelations
     * @return Collection
     */
    private function mapWithProjectExtraInfo($tasks, array $projectAdditionalRelations = []): Collection
    {
        $relations = collect([
            'typeClassifierValue',
            'subProjects.destinationLanguageClassifierValue',
            'subProjects.sourceLanguageClassifierValue',
            'tags',
            'clientInstitutionUser',
        ])->merge($projectAdditionalRelations)->unique();

        $projectIds = collect($tasks)->map(fn($task) => data_get($task, 'variables.project_id'));
        $projects = Project::query()->whereIn('id', $projectIds)
            ->with($relations->toArray())
            ->get();

        return collect($tasks)->map(function ($task) use ($projects) {
            if (empty($taskType = TaskType::tryFrom(data_get($task, 'variables.task_type')))) {
                return $task;
            }

            if (filled($projectId = data_get($task, 'variables.project_id')) && ($taskType === TaskType::Correcting || $taskType === TaskType::ClientReview)) {
                return [
                    ...$task,
                    'project' => $projects->firstWhere('id', $projectId)
                ];
            }

            return $task;
        });
    }

    private function mapWithTmKeysInfo(?array $task): ?array
    {
        $assignment = data_get($task, 'assignment');
        if (!$assignment instanceof Assignment) {
            return $task;
        }

        if (empty($institutionId = Auth::user()->institutionId)) {
            abort(Response::HTTP_UNAUTHORIZED, 'institution is not defined for active user');
        }

        if (empty($tmKeyIds = $assignment->subProject->catToolTmKeys()->pluck('key')->toArray())) {
            return $task;
        }

        try {
            $tmKeysMeta = $this->tmServiceApiClient->getTags($institutionId, $tmKeyIds);
            $tmKeysStats = $this->tmServiceApiClient->getTagsStats($institutionId);
        } catch (RequestException) {
            return $task;
        }

        return [
            ...$task,
            'tm_keys_meta' => $tmKeysMeta ?? [],
            'tm_keys_stats' => $tmKeysStats ?? []
        ];
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeVendorTaskCompletion(?Vendor $vendor, array $taskData): void
    {
        $taskType = data_get($taskData, 'variables.task_type');
        if ($taskType !== TaskType::Default->value) {
            throw new BadMethodCallException('Trying to authorize vendor task completion with wrong task type');
        }

        Gate::denyIf(empty($assignee = data_get($taskData, 'task.assignee')), 'Task can be completed only by assigned user');
        Gate::denyIf(empty($vendor), 'Active user is not a vendor');
        Gate::denyIf($assignee !== $vendor->institution_user_id, 'You are not assigned to the task');
    }

    private function getActiveVendor(): ?Vendor
    {
        if (empty($institutionUserId = Auth::user()->institutionUserId)) {
            return null;
        }

        return Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $institutionUserId)
            ->first();
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeProjectManagerTaskCompletion(array $taskData): void
    {
        $taskType = data_get($taskData, 'variables.task_type');
        if ($taskType !== TaskType::Correcting->value && $taskType !== TaskType::Review->value) {
            throw new BadMethodCallException('Trying to authorize PM task completion with wrong task type');
        }

        Gate::allowIf(Auth::hasPrivilege(PrivilegeKey::ManageProject->value), 'You do not have privilege to complete the task');
        Gate::denyIf(Auth::user()->institutionId !== data_get($taskData, 'variables.institution_id'));
    }

    private function getTaskProject(array $taskData, array $relations = []): ?Project
    {
        if (empty($projectId = data_get($taskData, 'variables.project_id'))) {
            return null;
        }
        $query = Project::withGlobalScope('policy', ProjectPolicy::scope());

        if (filled($relations)) {
            $query->with($relations);
        }

        return $query->find($projectId);
    }
}

class PaginationBuilder
{
    private int $perPage;
    private string $pageName;

    function __construct(?int $perPage = null)
    {
        $this->perPage = $perPage ?: 15;
        $this->pageName = 'page';
    }

    private function getCurrentPage(): int
    {
        return Paginator::resolveCurrentPage($this->pageName);
    }

    private function getCurrentPath(): string
    {
        return Paginator::resolveCurrentPath();
    }

    private function getPaginationOptions(): array
    {
        return [
            'path' => $this->getCurrentPath(),
            'pageName' => $this->pageName,
        ];
    }

    public function getPaginationParams(): array
    {
        return [
            'firstResult' => ($this->getCurrentPage() - 1) * $this->perPage,
            'maxResults' => $this->perPage,
        ];
    }

    public function toPaginator($items, $total)
    {
        $perPage = $this->perPage;
        $options = $this->getPaginationOptions();
        $currentPage = $this->getCurrentPage();

        return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items', 'total', 'perPage', 'currentPage', 'options'
        ));
    }
}
