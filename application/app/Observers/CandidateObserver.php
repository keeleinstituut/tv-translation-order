<?php

namespace App\Observers;

use App\Jobs\Workflows\DeleteCandidatesFromWorkflow;
use App\Models\Candidate;
use App\Models\Vendor;

class CandidateObserver
{
    /**
     * Handle the Candidate "created" event.
     */
    public function created(Candidate $candidate): void
    {
        //
    }

    /**
     * Handle the Candidate "updated" event.
     */
    public function updated(Candidate $candidate): void
    {
        //
    }

    /**
     * Handle the Candidate "deleted" event.
     */
    public function deleted(Candidate $candidate): void
    {
        /** @var Vendor $vendor */
        $vendor = $candidate->vendor()->withTrashed()->first();
        if (filled($candidate->assignment) && filled($vendor?->institution_user_id)) {
            DeleteCandidatesFromWorkflow::dispatch(
                $candidate->assignment,
                [$vendor?->institution_user_id]
            );
        }
    }

    /**
     * Handle the Candidate "restored" event.
     */
    public function restored(Candidate $candidate): void
    {
        //
    }

    /**
     * Handle the Candidate "force deleted" event.
     */
    public function forceDeleted(Candidate $candidate): void
    {
        //
    }
}
