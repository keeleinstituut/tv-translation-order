<?php

namespace App\Jobs;

use App\CatTools\MateCat\MateCatService;
use App\CatTools\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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

    public function __construct(private readonly array $subOrderMeta)
    {
    }

    public function handle(): void
    {
        $service = new MateCatService(
            new SubOrder($this->subOrderMeta)
        );

        foreach (range(1, self::RETRY_COUNT) as $_) {
            if ($service->handleProjectCreationStatusUpdate()) {
                return;
            }

            sleep(self::SECONDS_BETWEEN_TRY);
        }

        $this->release(self::REQUEUE_DELAY);
    }
}
