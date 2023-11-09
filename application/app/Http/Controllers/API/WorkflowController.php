<?php

namespace App\Http\Controllers\API;

use App\Enums\AssignmentStatus;
use App\Enums\PrivilegeKey;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\CompleteProjectReviewTaskRequest;
use App\Http\Requests\API\CompleteReviewTaskRequest;
use App\Http\Requests\API\WorkflowHistoryTaskListRequest;
use App\Http\Requests\API\WorkflowTaskListRequest;
use App\Http\Resources\TaskResource;
use App\Jobs\TrackProjectStatus;
use App\Jobs\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\Vendor;
use App\Policies\ProjectPolicy;
use App\Policies\VendorPolicy;
use App\Services\Workflows\WorkflowService;
use Auth;
use BadMethodCallException;
use DB;
use Gate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use KeycloakAuthGuard\Models\JwtPayloadUser;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;
use Throwable;
use Illuminate\Support\Collection;

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
            'processInstanceBusinessKeyLike' => 'workflow.%',
        ]);

        $tasks = WorkflowService::getTasks($params);
        $count = WorkflowService::getTasksCount($params)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

        return TaskResource::collection($pagination->toPaginator($data, $count));
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
            ...$this->buildAdditionalParams($requestParams),
            'processInstanceBusinessKeyLike' => 'workflow.%',
        ]);

        $tasks = WorkflowService::getHistoryTask($params);
        $count = WorkflowService::getHistoryTaskCount($params)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

        return TaskResource::collection($pagination->toPaginator($data, $count));
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/workflow/tasks/{id}/accept',
        description: 'Note: available only for tasks without assignee',
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

        $activeVendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', Auth::user()->institutionUserId)
            ->first();

        if (empty($activeVendor)) {
            abort(Response::HTTP_BAD_REQUEST, 'The active user is not a vendor');
        }

        // TODO: add check that the current vendor is candidate based on the identity-links/process instance variables
//        $candidates = data_get($taskData, 'variables.candidateUsers');
//        if (!in_array($activeVendor->id, $candidates)) {
//            abort(Response::HTTP_BAD_REQUEST, 'The vendor is not a candidate for the task');
//        }

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
        description: 'Note: available only for simple tasks',
        summary: 'Complete the task',
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function completeTask(string $id): TaskResource
    {
        $taskData = $this->getTaskDataOrFail($id);
        $taskType = data_get($taskData, 'variables.task_type', TaskType::Default->value);

        switch ($taskType) {
            case TaskType::Default->value:
                $this->authorizeVendorTaskCompletion($taskData);
                break;
            case TaskType::Correcting->value:
                $this->authorizeProjectManagerTaskCompletion($taskData);
                break;
            default:
                abort(Response::HTTP_BAD_REQUEST, 'The task can not be completed as it is review task');
        }

        WorkflowService::completeTask($id);

        /** @var Assignment $assignment */
        if (filled($assignment = $taskData['assignment'])) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();
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
    #[OA\Post(
        path: '/workflow/tasks/{id}/complete-review',
        description: 'Note: available only for review tasks',
        summary: 'Complete the task',
        requestBody: new OAH\RequestBody(CompleteReviewTaskRequest::class),
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function completeReviewTask(CompleteReviewTaskRequest $request): TaskResource
    {
        $taskId = $request->route('id');
        $taskData = $this->getTaskDataOrFail($taskId);

        if (data_get($taskData, 'variables.task_type', TaskType::Default->value) !== TaskType::Review->value) {
            abort(Response::HTTP_BAD_REQUEST, 'The task type is not review');
        }

        /** @var Assignment $assignment */
        if (empty($assignment = $taskData['assignment'])) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Assignment not found for the task');
        }

        $this->authorizeProjectManagerTaskCompletion($taskData);
        WorkflowService::completeReviewTask($taskId, $request->validated('accepted'));

        DB::transaction(function () use ($assignment, $request) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();

            $assignment->subProject->moveFinalFilesToProjectFinalFiles(
                $request->validated('final_file_id')
            );
        });

        TrackSubProjectStatus::dispatch($assignment->subProject);

        return TaskResource::make($taskData);
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/workflow/tasks/{id}/complete-project-review',
        description: 'Note: available only for project review tasks',
        summary: 'Complete the task',
        requestBody: new OAH\RequestBody(CompleteProjectReviewTaskRequest::class),
        tags: ['Workflow'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Task resource', response: Response::HTTP_OK)]
    public function completeProjectReviewTask(CompleteProjectReviewTaskRequest $request): TaskResource
    {
        $taskId = $request->route('id');
        $taskData = $this->getTaskDataOrFail($taskId);

        if (data_get($taskData, 'variables.task_type', TaskType::Default->value) !== TaskType::ClientReview->value) {
            abort(Response::HTTP_BAD_REQUEST, 'The task type is not client review');
        }

        if (empty($project = $this->getTaskProject($taskData))) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Could not determine project for the task');
        }

        $this->authorize('review', $project);

        WorkflowService::completeProjectReviewTask($taskId, $request->validated('accepted'));

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

    private function buildAdditionalParams(Collection $requestParams): Collection
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

        $assignedToMe = $requestParams->get('assigned_to_me', true);
        if (filled($assignedToMe)) {
            if ($assignedToMe) {
                $vendor = Vendor::getModel()
                    ->where('institution_user_id', Auth::user()->institutionUserId)
                    ->first();
                $params['assigned'] = true;
                // Use 'empty' as fallback since leaving fallback as null will be treated as all assignees
                $params['assignee'] = $vendor?->id || '--empty--';
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
    private function authorizeVendorTaskCompletion(array $taskData): void
    {
        Gate::denyIf(empty($institutionUserId = Auth::user()->institutionUserId), 'Unauthorized');
        $taskType = data_get($taskData, 'variables.task_type');
        if ($taskType !== TaskType::Default->value) {
            throw new BadMethodCallException('Trying to authorize vendor task completion with wrong task type');
        }

        Gate::denyIf(empty($assignee = data_get($taskData, 'task.assignee')), 'Task can be completed only by assigned user');

        $activeVendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $institutionUserId)
            ->first();

        Gate::denyIf(empty($activeVendor), 'Active user is not a vendor');
        Gate::denyIf($assignee !== $activeVendor->id, 'You are not assigned to the task');
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

        Gate::allowIf(Auth::hasPrivilege(PrivilegeKey::ManageProject->value));
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
