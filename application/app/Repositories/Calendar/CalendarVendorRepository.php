<?php

namespace App\Repositories\Calendar;

use App\Models\Vendor;
use App\Models\VendorCalendarImport;
use App\Models\VendorEmergencySchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class CalendarVendorRepository
{
    /**
     * Get vendor IDs that have an imported calendar covering the given period.
     *
     * @param  Collection<int, string>  $vendorIds
     * @return Collection<int, string>
     */
    public function getVendorIdsWithImportInPeriod(Collection $vendorIds, Carbon $start, Carbon $end): Collection
    {
        if ($vendorIds->isEmpty()) {
            return collect();
        }

        return VendorCalendarImport::whereIn('vendor_id', $vendorIds)
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start)
            ->distinct()
            ->pluck('vendor_id');
    }

    /**
     * Get emergency schedules for multiple vendors, grouped by vendor_id.
     *
     * @param  Collection<int, string>  $vendorIds
     * @return Collection<string, Collection<int, VendorEmergencySchedule>>
     */
    public function getEmergencySchedulesForVendors(Collection $vendorIds, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        if ($vendorIds->isEmpty()) {
            return collect();
        }

        return VendorEmergencySchedule::whereIn('vendor_id', $vendorIds)
            ->where('start_date', '<=', $dateTo)
            ->where('end_date', '>=', $dateFrom)
            ->orderBy('start_date')
            ->get()
            ->groupBy('vendor_id');
    }
}
