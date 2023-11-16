<?php

namespace App\Jobs\Workflows;

use App\Enums\TaskType;
use App\Models\Project;
use App\Services\Workflows\WorkflowService;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateProjectClientInsideWorkflow implements ShouldQueue
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
     * @throws RequestException
     */
    public function handle(): void
    {
        if (!$this->project->workflow()->isStarted()) {
            return;
        }

        $this->project->workflow()->updateVariable(
            'client_institution_user_id',
            $this->project->client_institution_user_id
        );


        $searchResult = $this->project->workflow()->getTasksSearchResult([
            'processVariables' => [
                'name' => 'task_type',
                'value' => TaskType::ClientReview->value,
                'operator' => 'eq'
            ]
        ]);

        if ($searchResult->getCount() === 0) {
            return;
        }

        foreach ($searchResult->getTasks() as $taskData) {
            $taskType = TaskType::tryFrom(data_get($taskData, 'variables.task_type'));

            if (empty($taskType) || $taskType !== TaskType::ClientReview) {
                throw new DomainException('Task type is not defined or incorrect');
            }

            WorkflowService::setAssignee(
                data_get($taskData, 'task.id'),
                $this->project->client_institution_user_id
            );
        }
    }
}
