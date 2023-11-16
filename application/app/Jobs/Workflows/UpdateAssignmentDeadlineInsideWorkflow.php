<?php

namespace App\Jobs\Workflows;

use App\Enums\TaskType;
use App\Models\Assignment;
use App\Services\Workflows\WorkflowService;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateAssignmentDeadlineInsideWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment)
    {
    }

    /**
     * Execute the job.
     * @throws RequestException
     */
    public function handle(): void
    {
        $workflow = $this->assignment->subProject->workflow();
        if (!$workflow->isStarted()) {
            return;
        }

        $workflow->syncVariables();

        $searchResult = $workflow->getTasksSearchResult([
            'processVariables' => [
                'name' => 'assignment_id',
                'value' => $this->assignment->id,
                'operator' => 'eq'
            ]
        ]);

        if ($searchResult->getCount() === 0) {
            return;
        }

        if ($searchResult->getCount() > 1) {
            throw new DomainException('Assignment has multiple tasks inside the workflow');
        }

        $taskData = $searchResult->getTasks()->get(0);
        WorkflowService::updateTask(data_get($taskData, 'task.id'), [
            'due' => $this->assignment->deadline_at?->toIso8601String()
        ]);
    }
}
