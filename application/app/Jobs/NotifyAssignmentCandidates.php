<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class NotifyAssignmentCandidates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if (filled($this->assignment->assigned_vendor_id)) {
            return;
        }

        /** We need to notify candidates only in case if tasks are populated */
        if ($this->assignment->job_definition_id !== $this->assignment->subProject->active_job_definition_id) {
            return;
        }

        $this->assignment->candidates->each(function (Candidate $candidate) {
            if ($candidate->status === CandidateStatus::New) {
                // TODO: send email notification
                $candidate->status = CandidateStatus::SubmittedToVendor;
                $candidate->saveOrFail();
            }
        });
    }
}
