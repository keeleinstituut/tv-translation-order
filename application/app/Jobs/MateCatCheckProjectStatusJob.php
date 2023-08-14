<?php

namespace App\Jobs;

use App\Models\SubProject;
use App\Services\CAT\MateCatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MateCatCheckProjectStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private SubProject $subProject;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(SubProject $subProject)
    {
        $this->subProject = $subProject;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $times = 3;

        for ($i = 0; $i < $times; $i++) {
            $successful = $this->checkDoneStatusAndUpdate();

            if ($successful) {
                return;
            }
            sleep(5);
        }

        self::dispatch($this->subProject)->delay(now()->addMinutes(1));
    }

    private function checkDoneStatusAndUpdate()
    {
        $statusResponse = $this->subProject->cat()->propagateStatus();
        $status = $statusResponse['status'];

        if ($status == MateCatService::ANALYSIS_STATUS_DONE) {
            $this->subProject->cat()->propagateUrls();
            $this->subProject->save();

            return true;
        }

        return false;
    }
}
