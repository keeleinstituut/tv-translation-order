<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\WorkflowHistoryTaskListRequest;
use App\Http\Requests\API\WorkflowTaskListRequest;
use App\Http\Resources\TaskResource;
use App\Models\Assignment;
use App\Models\Vendor;
use App\Policies\AssignmentPolicy;
use App\Policies\VendorPolicy;
use App\Services\Workflows\WorkflowService;
use Auth;
use DB;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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
    public function getTasks(WorkflowTaskListRequest $request)
    {
        $requestParams = collect($request->validated());
        $pagination = new PaginationBuilder();

        $params = collect([
            ...$pagination->getPaginationParams(),
            ...$this->buildAdditionalParams($requestParams),
            'processInstanceBusinessKeyLike' => 'workflow.%',
        ]);

        $tasks = WorkflowService::getTask($params);
        $count = WorkflowService::getTaskCount($params)['count'];

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
    public function getHistoryTasks(WorkflowHistoryTaskListRequest $request)
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
        tags: ['Workflow management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: TaskResource::class, description: 'Updated task', response: Response::HTTP_OK)]
    public function acceptTask(string $id): TaskResource
    {
        $task = WorkflowService::getTask(['id' => $id]);
        $assignment = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
            ->find($task['assignment_id']);


        $institutionUserId = Auth::user()->institutionUserId;
        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $institutionUserId)
            ->first();

        if (!in_array($vendor->id, $task['candidates'])) {
            abort(Response::HTTP_BAD_REQUEST, 'The vendor is not a candidate for the task');
        }

        WorkflowService::setAssignee($id, $vendor->id);

        if (filled($assignment)) {
            DB::transaction(function () use ($assignment, $vendor) {
                $assignment->assigned_vendor_id = $vendor->id;
                $assignment->saveOrFail();
            });
        }

        return TaskResource::make($task);
    }

    public function completeTask(string $id)
    {
        return WorkflowService::completeTask($id);
    }

    private function buildAdditionalParams(Collection $requestParams) {
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
                $params['assignee'] = $vendor->id;
            } else {
                $params['unassigned'] = true;
            }
        }

        return $params;
    }

    private function fetchVariableInstancesForTasks(array $tasks) {
        $executionIds = collect($tasks)->pluck('executionId')->implode(',');
        return WorkflowService::getVariableInstance([
            'executionIdIn' => $executionIds,
        ]);
    }

    private function mapWithVariables($tasks, $variableInstances) {
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

    private function mapWithExtraInfo($tasks) {
        $assignmentIds = collect($tasks)->map(fn ($task) => data_get($task, 'variables.assignment_id'));
        $assignments = Assignment::getModel()
            ->whereIn('id', $assignmentIds)
            ->with([
                'subProject',
                'subProject.project',
                'subProject.project.typeClassifierValue',
                'subProject.sourceLanguageClassifierValue',
                'subProject.destinationLanguageClassifierValue',
            ])
            ->get();

        return collect($tasks)->map(function ($task) use ($assignments) {
           return [
               ...$task,
               'assignment' => $assignments->firstWhere('id', data_get($task, 'variables.assignment_id'))
           ];
        });
    }

}

class PaginationBuilder {
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
