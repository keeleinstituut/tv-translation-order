<?php

namespace App\Jobs\Workflows;

use App\Models\Project;
use App\Models\SubProject;
use App\Services\Workflows\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateProjectDeadlineInsideWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Project $project)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $workflow = $this->project->workflow();
        if (!$workflow->isStarted()) {
            return;
        }

        $searchResult = $workflow->getTasksSearchResult([
            'processInstanceId' => $this->project->workflow_instance_ref
        ]);

        if ($searchResult->getCount() === 0) {
            return;
        }

        foreach ($searchResult->getTasks() as $taskData) {
            $taskId = data_get($taskData, 'task.id');
            // TODO: update deadline for project workflow tasks
        }
    }
}
