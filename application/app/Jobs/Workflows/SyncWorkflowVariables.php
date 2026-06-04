<?php

namespace App\Jobs\Workflows;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWorkflowVariables implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment)
    {
    }

    /**
     * Execute the job.
     * @throws RequestException
     */
    public function handle(): void
    {
        $workflow = $this->assignment->subProject->workflow();
        if (!$workflow->isStarted()) {
            return;
        }

        $workflow->syncVariables();
    }
}
