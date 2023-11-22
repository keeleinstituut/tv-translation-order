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
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment, private readonly array $candidatesInstitutionUserIds)
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

        foreach ($this->candidatesInstitutionUserIds as $candidateInstitutionUserId) {
            WorkflowService::deleteIdentityLink(
                data_get($taskData, 'task.id'),
                $candidateInstitutionUserId,
                'candidate'
            );
        }
    }
}
