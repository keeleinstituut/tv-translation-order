<?php

namespace App\Jobs;

use App\Models\SubProject;
use App\Services\CatTools\MateCat\MateCatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Job is needed to track progress of the project.
 */
class TrackMateCatProjectProgress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    const REQUEUE_DELAY_SECONDS = 600; // 10 minutes.

    public function __construct(private readonly SubProject $subProject)
    {
    }

    public function handle(): void
    {
        $service = new MateCatService($this->subProject);
        $service->updateProjectInfo();
        foreach ($service->getUserTasks() as $job) {
            if ($job->progressPercentage < 100) {
                $this->release(self::REQUEUE_DELAY_SECONDS);
                return;
            }
        }
    }
}
