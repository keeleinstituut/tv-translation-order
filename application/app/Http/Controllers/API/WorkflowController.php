<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Services\WorkflowService;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

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
}
