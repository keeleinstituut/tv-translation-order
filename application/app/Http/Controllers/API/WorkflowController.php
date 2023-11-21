<?php

namespace App\Http\Controllers\API;

use App\Enums\AssignmentStatus;
use App\Enums\CandidateStatus;
use App\Enums\PrivilegeKey;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\WorkflowHistoryTaskListRequest;
use App\Http\Requests\API\WorkflowTaskListRequest;
use App\Http\Resources\TaskResource;
use App\Jobs\Workflows\TrackProjectStatus;
use App\Jobs\Workflows\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Media;
use App\Models\Project;
use App\Models\Vendor;
use App\Policies\ProjectPolicy;
use App\Policies\VendorPolicy;
use App\Services\Workflows\ProjectWorkflowProcessInstance;
use App\Services\Workflows\WorkflowService;
use Auth;
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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorkflowController extends Controller
{
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
    public function getTasks(WorkflowTaskListRequest $request): AnonymousResourceCollection
    {
        $requestParams = collect($request->validated());
        $pagination = new PaginationBuilder();

        $params = collect([
            ...$pagination->getPaginationParams(),
            ...$this->buildAdditionalParams($requestParams),
        ]);


        $tasks = WorkflowService::getTasks($params);
        $count = WorkflowService::getTasksCount($params)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

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

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

        return TaskResource::make($data->first());
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
        $pagination = new PaginationBuilder();

        $params = collect([
            ...$pagination->getPaginationParams(),
            ...$this->buildAdditionalParams($requestParams, true),
        ]);

        $tasks = WorkflowService::getHistoryTask($params);
        $count = WorkflowService::getHistoryTaskCount($params)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

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

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

        return TaskResource::make($data->first());
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
    public function acceptTask(string $id): TaskResource
    {
        $taskData = $this->getTaskDataOrFail($id);

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

        $candidatesVendorIds = collect(WorkflowService::getIdentityLinks($id, 'candidate'))
            ->pluck('userId');

        if (!$candidatesVendorIds->contains($activeVendor->id)) {
            abort(Response::HTTP_BAD_REQUEST, 'The vendor is not a candidate for the task');
        }

        WorkflowService::setAssignee($id, $activeVendor->id);
        /** @var Assignment $assignment */
        if (filled($assignment = $taskData['assignment'])) {
            DB::transaction(function () use ($assignment, $activeVendor) {
                $assignment->assigned_vendor_id = $activeVendor->id;
                $assignment->saveOrFail();
            });

            TrackSubProjectStatus::dispatch($assignment->subProject);
        }

        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/workflow/tasks/{id}/complete',
        description: 'Note: `accepted` param is required in case if task has type `CLIENT_REVIEW` or `REVIEW`. The `final_file_id` param is required in case if task has type `REVIEW`',
        summary: 'Complete the task',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                required: [],
                properties: [
                    new OA\Property(property: 'accepted', type: 'boolean'),
                    new OA\Property(property: 'final_file_id', type: 'array', items: new OA\Items(type: 'integer')),
                ]
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
        $taskType = data_get($taskData, 'variables.task_type', TaskType::Default->value);

        return match ($taskType) {
            TaskType::Default->value, TaskType::Correcting->value => $this->completeDefaultTask($taskData),
            TaskType::Review->value => $this->completeReviewTask($request, $taskData),
            TaskType::ClientReview->value => $this->completeProjectReviewTask($request, $taskData),
            default => throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unexpected task type')
        };
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    private function completeDefaultTask(array $taskData): TaskResource
    {
        $id = data_get($taskData, 'task.id');
        $taskType = TaskType::tryFrom(data_get($taskData, 'variables.task_type'));

        switch ($taskType) {
            case TaskType::Default:
                $vendor = $this->getActiveVendor();
                $this->authorizeVendorTaskCompletion($vendor, $taskData);
                break;
            case TaskType::Correcting:
                $this->authorizeProjectManagerTaskCompletion($taskData);
                break;
            default:
                throw new InvalidArgumentException('Task with incorrect type passed');
        }

        WorkflowService::completeTask($id);

        /** @var Assignment $assignment */
        if (filled($assignment = $taskData['assignment'])) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();

            if (isset($vendor) && filled($vendor)) {
                /** @var Candidate $candidate */
                $candidate = $assignment->candidates()->where('vendor_id', $vendor->id)
                    ->first();

                if (filled($candidate)) {
                    $candidate->status = CandidateStatus::Done;
                    $candidate->saveOrFail();
                }
            }

            TrackSubProjectStatus::dispatch($assignment->subProject);
        } elseif (filled($project = $this->getTaskProject($taskData))) {
            TrackProjectStatus::dispatch($project);
        }

        return TaskResource::make($taskData);
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
        WorkflowService::completeReviewTask(data_get($taskData, 'task.id'), $validated['accepted']);

        DB::transaction(function () use ($assignment, $validated) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();

            $assignment->subProject->moveFinalFilesToProjectFinalFiles(
                $validated['final_file_id']
            );
        });

        TrackSubProjectStatus::dispatch($assignment->subProject);

        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    private function completeProjectReviewTask(Request $request, array $taskData): TaskResource
    {
        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
        ]);

        if (data_get($taskData, 'variables.task_type', TaskType::Default->value) !== TaskType::ClientReview->value) {
            abort(Response::HTTP_BAD_REQUEST, 'The task type is not client review');
        }

        if (empty($project = $this->getTaskProject($taskData))) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Could not determine project for the task');
        }

        $this->authorize('review', $project);

        WorkflowService::completeProjectReviewTask(data_get($taskData, 'task.id'), $validated['accepted']);

        TrackProjectStatus::dispatch($project);

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
        if (empty($this->mapWithExtraInfo($data)[0])) {
            abort(Response::HTTP_NOT_FOUND, 'Task data not found');
        }

        return $this->mapWithExtraInfo($data)[0];
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
                'value' => $langPair['dst'],
                'operator' => 'eq',
            ]);
        }

        if ($param = $requestParams->get('type_classifier_value_id')) {
            $typeClassifierValueId = collect($param)->first();
            $processVariablesFilter->push([
                'name' => 'type_classifier_value_id',
                'value' => $typeClassifierValueId,
                'operator' => 'eq',
            ]);
        }

        if ($sortBy = $requestParams->get('sort_by')) {
            $sortOrder = $requestParams->get('sort_order', 'asc');
            $sortingParams->push([
                'sortBy' => 'processVariable',
                'sortOrder' => $sortOrder,
                'parameters' => [
                    'variable' => $sortBy,
                    'type' => 'string',
                ]
            ]);
        }

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

        $assignedToMe = $requestParams->get('assigned_to_me', true);
        if (!$requestParams->get('skip_assigned_param') && filled($assignedToMe)) {
            if ($assignedToMe) {
                $vendor = Vendor::getModel()
                    ->where('institution_user_id', Auth::user()->institutionUserId)
                    ->first();
                $params['assigned'] = true;
                // Use 'empty' as fallback since leaving fallback as null will be treated as all assignees
                $assigneeKey = $forHistoricTasks ? 'taskAssignee' : 'assignee';
                $params[$assigneeKey] = $vendor?->id ?? '--empty--';
            } else {
                $params['unassigned'] = true;
            }
        }

        return $params;
    }

    private function fetchVariableInstancesForTasks(array $tasks)
    {
        $executionIds = collect($tasks)->pluck('executionId')->implode(',');
        return WorkflowService::getVariableInstance([
            'executionIdIn' => $executionIds,
        ]);
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

    private function mapWithExtraInfo($tasks): Collection
    {
        $assignmentIds = collect($tasks)->map(fn($task) => data_get($task, 'variables.assignment_id'));
        $assignments = Assignment::getModel()
            ->whereIn('id', $assignmentIds)
            ->with([
                'subProject',
                'subProject.project',
                'subProject.project.typeClassifierValue',
                'subProject.sourceLanguageClassifierValue',
                'subProject.destinationLanguageClassifierValue',
            ])->get();

        return collect($tasks)->map(function ($task) use ($assignments) {
            return [
                ...$task,
                'assignment' => $assignments->firstWhere('id', data_get($task, 'variables.assignment_id'))
            ];
        });
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
        Gate::denyIf($assignee !== $vendor->id, 'You are not assigned to the task');
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

    private function getTaskProject(array $taskData): ?Project
    {
        if (empty($projectId = data_get($taskData, 'variables.project_id'))) {
            return null;
        }

        return Project::withGlobalScope('policy', ProjectPolicy::scope())->find($projectId);
    }
}

class PaginationBuilder
{
    private int $perPage;
    private string $pageName;

    function __construct()
    {
        $this->perPage = 15;
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
