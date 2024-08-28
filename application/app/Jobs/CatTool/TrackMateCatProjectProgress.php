<?php

namespace App\Jobs\CatTool;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\SubProject;
use App\Services\CatTools\MateCat\MateCat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job is needed to track progress of the project.
 */
class TrackMateCatProjectProgress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const REQUEUE_DELAY_SECONDS = 60 * 10; // 10 minutes.

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 0;

//    public $backoff = self::REQUEUE_DELAY_SECONDS;

    public function __construct(private readonly SubProject $subProject)
    {
    }

    public function handle(): void
    {
        if ($this->noNeedToTrack($this->subProject->project)) {
            return;
        }

        $service = new MateCat($this->subProject);
        $service->updateProjectProgress();

        foreach ($this->subProject->catToolJobs as $job) {
            if ($job->progress_percentage < 100) {
                $this->release(self::REQUEUE_DELAY_SECONDS);

                return;
            }
        }
    }

    public function noNeedToTrack(Project $project): bool
    {
        return in_array($project->status, [
            ProjectStatus::Accepted,
            ProjectStatus::Cancelled,
            ProjectStatus::SubmittedToClient,
        ]);
    }
}
