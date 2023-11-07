<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\AssignmentResource;
use App\Http\Resources\TaskResource;
use App\Models\Assignment;
use App\Models\Vendor;
use App\Policies\AssignmentPolicy;
use App\Policies\VendorPolicy;
use App\Services\Workflows\WorkflowService;
use Auth;
use DB;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Response;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;
use Throwable;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getTasks()
    {
        $perPage = 15;
        $pageName = 'page';

        $currentPage = Paginator::resolveCurrentPage($pageName);
        //        dd(($currentPage - 1) * $perPage);

        $params = [
            //            'sortBy' => 'id',
            //            'sortOrder' => 'asc',
            'firstResult' => ($currentPage - 1) * $perPage,
            'maxResults' => $perPage,
        ];

        $items = WorkflowService::getTask($params);
        $count = WorkflowService::getTaskCount($params)['count'];

        $total = $count;
        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ];

        $paginator = Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items', 'total', 'perPage', 'currentPage', 'options'
        ));

        return TaskResource::collection($paginator);
        //        return WorkflowService::getTask();
    }

    private function paginate($items, $perPage, $currentPage, $options = [])
    {
        return new LengthAwarePaginator($items, $total, $perPage, $currentPage, $options);
    }

    public function getHistoryTasks()
    {
        $params = [];
        $items = WorkflowService::getHistoryTask($params);
        $pageName = 'page';
        $total = WorkflowService::getHistoryTaskCount($params)['count'];
        $perPage = 1;
        $currentPage = Paginator::resolveCurrentPage($pageName);
        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ];

        $paginator = Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items', 'total', 'perPage', 'currentPage', 'options'
        ));

        return TaskResource::collection($paginator);
        //        return TaskResource::collection($items);
    }

    public function getTask(string $id)
    {
        return WorkflowService::getTask([
            'taskId' => $id,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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

    public function completeTask(string $id)
    {
        return WorkflowService::completeTask($id);
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
}
