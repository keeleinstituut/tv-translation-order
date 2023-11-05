<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Models\Project;
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

        if ($this->project->status === ProjectStatus::Registered && $this->workflowHasClientReviewTask()) {
            $this->project->status = ProjectStatus::SubmittedToClient;
            $this->project->saveOrFail();
        }
    }

    /**
     * TODO: implement better way of checking is there review task.
     *
     * @return bool
     */
    private function workflowHasClientReviewTask(): bool
    {
        $tasks = collect($this->project->workflow()->getTasks());
        return $tasks->keyBy('name')->has('Tõlketellimuse vastuvõtmine');
    }

}
