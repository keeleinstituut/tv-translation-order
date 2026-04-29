<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class AutoDeclineVendorTaskProposal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $candidateId)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $candidate = Candidate::lockForUpdate()->find($this->candidateId);

            if (!$candidate || $candidate->status !== CandidateStatus::SubmittedToVendor) {
                return;
            }

            if (filled($candidate->assignment?->assigned_vendor_id) && $candidate->assignment->assigned_vendor_id !== $candidate->vendor_id) {
                return;
            }

            $candidate->status = CandidateStatus::Rejected;
            $candidate->saveOrFail();

            if ($candidate->assignment->subProject->project->is_calendar_project) {
                ProcessCandidatesNotificationCycle::dispatch($candidate->assignment)
                    ->afterCommit();
            }
        });

    }
}
