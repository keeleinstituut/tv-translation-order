<?php

namespace App\Jobs\Workflows;

use App\Models\Assignment;
use App\Services\Workflows\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateSharedAssignmentInstitutionInsideWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

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

        // `syncVariables()` only rewrites the `subProject` process variable. The task's
        // `institution_id` is a task-local variable captured from `${subProcess.institution_id}`
        // when the task was created and is not re-evaluated, so update it on the live task
        // directly. Otherwise the partner institution that accepted the offer can't see the task.
        if (empty($taskData = $workflow->getTaskDataBasedOnAssignment($this->assignment))) {
            return;
        }

        WorkflowService::updateTaskLocalVariable(
            data_get($taskData, 'task.id'),
            'institution_id',
            [
                'value' => $this->assignment->currentOutsourceRequest?->acceptedOffer?->institution_id
                    ?? $this->assignment->subProject->project->institution_id,
                'type' => 'String',
            ]
        );
    }
}
