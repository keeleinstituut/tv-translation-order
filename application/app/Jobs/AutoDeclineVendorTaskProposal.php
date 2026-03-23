<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Services\Calendar\CalendarVendorTaskProposalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    public function handle(CalendarVendorTaskProposalService $service): void
    {
        $candidate = Candidate::find($this->candidateId);

        if (!$candidate || $candidate->status !== CandidateStatus::SubmittedToVendor) {
            return;
        }

        if (filled($candidate->assignment?->assigned_vendor_id)) {
            return;
        }

        $service->handleDecline($candidate);
    }
}
