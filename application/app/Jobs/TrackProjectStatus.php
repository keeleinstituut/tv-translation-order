<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Services\Workflows\Tasks\TasksSearchResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TrackProjectStatus implements ShouldQueue
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
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->project->status === ProjectStatus::Cancelled) {
            return;
        }

        if ($this->project->status === ProjectStatus::Accepted) {
            return;
        }

        $searchResults = $this->project->workflow()->getTasksSearchResult();
        if ($searchResults->getCount() === 0) {
            $this->project->status = ProjectStatus::Accepted;
            $this->project->saveOrFail();
            return;
        }

        if ($this->workflowHasClientReviewTask($searchResults)) {
            $this->project->status = $this->project->status === ProjectStatus::Rejected ?
                ProjectStatus::Corrected : ProjectStatus::SubmittedToClient;
            $this->project->saveOrFail();
            return;
        }

        if ($this->workflowHasCorrectingTask($searchResults)) {
            $this->project->status = ProjectStatus::Rejected;
            $this->project->saveOrFail();
        }
    }

    /**
     * @param TasksSearchResult $searchResults
     * @return bool
     */
    private function workflowHasClientReviewTask(TasksSearchResult $searchResults): bool
    {
        return $searchResults->getTasks()->pluck('variables.task_type')
            ->contains(TaskType::ClientReview->value);
    }

    /**
     * @param TasksSearchResult $searchResults
     * @return bool
     */
    private function workflowHasCorrectingTask(TasksSearchResult $searchResults): bool
    {
        return $searchResults->getTasks()->pluck('variables.task_type')
            ->contains(TaskType::Correcting->value);
    }

}
