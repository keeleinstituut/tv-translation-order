<?php

namespace App\Console\Commands;

use App\Enums\CandidateStatus;
use App\Jobs\AutoDeclineVendorTaskProposal;
use App\Jobs\ProcessCandidatesNotificationCycle;
use App\Models\CalendarSetting;
use App\Models\Candidate;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpireOverdueProposals extends Command
{
    protected $signature = 'app:expire-overdue-proposals';

    protected $description = 'Auto-decline external vendor proposals that exceeded their reaction time';

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $reactionTimes = CalendarSetting::pluck('reaction_time_seconds', 'institution_id');

        $candidates = Candidate::query()
            ->where('status', CandidateStatus::SubmittedToVendor)
            ->whereNotNull('notified_at')
            ->whereHas('assignment', fn($q) => $q->whereNull('assigned_vendor_id'))
            ->whereHas('vendor', fn($q) => $q->whereNotNull('company_name'))
            ->with('assignment.subProject.project', 'vendor')
            ->get();

        foreach ($candidates as $candidate) {
            $institutionId = $candidate->vendor?->institutionUser?->institution_id;
            $reactionTime = $reactionTimes->get($institutionId, ProcessCandidatesNotificationCycle::DEFAULT_REACTION_TIME_SECONDS);
            $expiresAt = $candidate->notified_at->addSeconds($reactionTime);

            if (Carbon::now()->greaterThan($expiresAt)) {
                AutoDeclineVendorTaskProposal::dispatchSync($candidate->id);
            }
        }
    }
}
