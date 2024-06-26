<?php

namespace App\Jobs\Workflows;

use App\Models\Project;
use App\Models\SubProject;
use App\Services\Workflows\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\HttpFoundation\Response;

class UpdateProjectDeadlineInsideWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Project $project)
    {
    }

    /**
     * Execute the job.
     * @throws RequestException
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
            WorkflowService::updateTask(
                data_get($taskData, 'task.id'),
                array_merge(
                    data_get($taskData, 'task'), [
                    'due' => $this->project->deadline_at?->format(WorkflowService::DATETIME_FORMAT)
                ])
            );
        }
    }
}
