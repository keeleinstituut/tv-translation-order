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

class DeleteCandidatesFromWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment, private readonly array $candidatesIds)
    {
    }

    /**
     * Execute the job.
     * @throws RequestException
     */
    public function handle(): void
    {
        $workflow = $this->assignment->subProject?->workflow();
        if (empty($workflow) || !$workflow->isStarted()) {
            return;
        }

        if (empty($taskData = $workflow->getTaskDataBasedOnAssignment($this->assignment))) {
            return;
        }

        foreach ($this->candidatesIds as $candidateId) {
            WorkflowService::deleteIdentityLink(
                data_get($taskData, 'task.id'),
                $candidateId,
                'candidate'
            );
        }
    }
}
