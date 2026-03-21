<?php

namespace App\Observers;

use App\Jobs\RefreshVendorLanguageCoverageJob;
use App\Models\Candidate;
use App\Models\Vendor;

class VendorObserver
{
    /**
     * Handle the Vendor "created" event.
     */
    public function created(Vendor $vendor): void
    {
        //
    }

    /**
     * Handle the Vendor "updated" event.
     */
    public function updated(Vendor $vendor): void
    {
        if ($vendor->wasChanged(['company_name', 'institution_user_id'])) {
            RefreshVendorLanguageCoverageJob::dispatch();
        }
    }

    /**
     * Handle the Vendor "deleted" event.
     */
    public function deleted(Vendor $vendor): void
    {
        $vendor->prices->each(fn ($price) => $price->delete());
        $vendor->candidates->each(fn (Candidate $candidate) => $candidate->delete());
    }

    /**
     * Handle the Vendor "restored" event.
     */
    public function restored(Vendor $vendor): void
    {
        RefreshVendorLanguageCoverageJob::dispatch();
    }

    /**
     * Handle the Vendor "force deleted" event.
     */
    public function forceDeleted(Vendor $vendor): void
    {
        //
    }
}
