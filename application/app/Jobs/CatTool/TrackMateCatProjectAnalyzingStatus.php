<?php

namespace App\Jobs\CatTool;

use App\Models\SubProject;
use App\Services\CatTools\MateCat\MateCat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TrackMateCatProjectAnalyzingStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const RETRY_COUNT = 3;

    const SECONDS_BETWEEN_TRY = 5;

    const REQUEUE_DELAY_SECONDS = 60;

    /**
     * The number of times the job may be attempted is not limited
     */
    public int $tries = 0;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 3;

    public function __construct(private readonly SubProject $subProject)
    {
    }

    public function handle(): void
    {
        $service = new MateCat($this->subProject);
        foreach (range(1, self::RETRY_COUNT) as $_) {
            if ($service->checkProjectAnalyzed()) {
                return;
            }

            sleep(self::SECONDS_BETWEEN_TRY);
        }
        $this->release(self::REQUEUE_DELAY_SECONDS);
    }

    public function retryUntil(): Carbon
    {
        return now()->addMinutes(60); // 1 hour
    }
}
