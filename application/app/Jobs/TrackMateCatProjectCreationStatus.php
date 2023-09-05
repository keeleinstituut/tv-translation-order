<?php

namespace App\Jobs;

use App\Models\SubProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrackMateCatProjectCreationStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const RETRY_COUNT = 3;

    const SECONDS_BETWEEN_TRY = 5;

    const REQUEUE_DELAY = 10;

    public function __construct(private readonly SubProject $subProject)
    {
    }

    public function handle(): void
    {
        $service = $this->subProject->cat();
        foreach (range(1, self::RETRY_COUNT) as $_) {
            if ($service->checkProjectCreationStatusUpdate()) {
                return;
            }

            sleep(self::SECONDS_BETWEEN_TRY);
        }

        $this->release(self::REQUEUE_DELAY);
    }
}
