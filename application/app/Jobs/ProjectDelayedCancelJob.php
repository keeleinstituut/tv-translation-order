<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProjectDelayedCancelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int CANCELLATION_DELAY_SECONDS = 60;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $projectId)
    {
    }

    public function handle(): void
    {
        $project = Project::with(['subProjects.assignments.calendarEntry'])->find($this->projectId);

        if (!$project || !$project->cancellation_pending_at || $project->status === ProjectStatus::Cancelled) {
            return;
        }

        DB::transaction(function () use ($project) {
            $project->status = ProjectStatus::Cancelled;
            $project->saveOrFail();

            if ($project->workflow()->isStarted()) {
                $project->workflow()->cancel($project->cancellation_reason ?? '');
            }
        });

        $project->cancellation_pending_at = null;
        $project->saveQuietly();
    }
}
