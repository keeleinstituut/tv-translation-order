<?php

namespace App\Services\Workflows\Tasks;

use App\Models\Assignment;
use App\Services\Workflows\WorkflowService;
use Illuminate\Support\Collection;

class WorkflowTasksDataProvider
{

    /**
     * @param array|Collection $params
     * @return TasksSearchResult
     */
    public function search(array|Collection $params): TasksSearchResult
    {
        $params = is_array($params) ? collect($params) : $params;
        $tasks = WorkflowService::getTasks($params);
        $count = WorkflowService::getTasksCount($params)['count'];

        $variableInstances = $this->fetchVariableInstancesForTasks($tasks);

        $data = $this->mapWithVariables($tasks, $variableInstances);
        $data = $this->mapWithExtraInfo($data);

        return new TasksSearchResult($data, $count);
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
        $assignmentIds = collect($tasks)->map(fn ($task) => data_get($task, 'variables.assignment_id'));
        $assignments = Assignment::query()
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
