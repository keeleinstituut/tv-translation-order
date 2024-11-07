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
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

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

        if (empty($taskData = $workflow->getTaskDataBasedOnAssignment($this->assignment))) {
            return;
        }

        WorkflowService::updateTask(
            data_get($taskData, 'task.id'),
            array_merge(
                data_get($taskData, 'task'), [
                'due' => $this->assignment->deadline_at?->format(WorkflowService::DATETIME_FORMAT)
            ])
        );
    }
}
