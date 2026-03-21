<?php

namespace App\Observers;

use App\Jobs\RefreshVendorLanguageCoverageJob;
use App\Models\Price;

class PriceObserver
{
    public function saved(Price $price): void
    {
        RefreshVendorLanguageCoverageJob::dispatch();
    }

    public function deleted(Price $price): void
    {
        RefreshVendorLanguageCoverageJob::dispatch();
    }

    public function restored(Price $price): void
    {
        RefreshVendorLanguageCoverageJob::dispatch();
    }
}
