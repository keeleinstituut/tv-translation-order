<?php

namespace App\Observers;

use App\Jobs\RefreshVendorLanguageCoverageJob;
use App\Models\CachedEntities\InstitutionUser;

class InstitutionUserObserver
{
    public function saved(InstitutionUser $institutionUser): void
    {
        RefreshVendorLanguageCoverageJob::dispatch();
    }

    public function deleted(InstitutionUser $institutionUser): void
    {
        RefreshVendorLanguageCoverageJob::dispatch();
    }
}
