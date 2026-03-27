<?php

namespace App\Services\Calendar;

use App\Jobs\ExpirePrebookJob;
use App\Models\Assignment;
use App\Models\VendorCalendarEntry;
use Illuminate\Support\Carbon;

class PrebookService
{
    public const int PREBOOK_DURATION_MINUTES = 10;

    public function create(
        string $vendorId,
        Carbon $startAt,
        Carbon $endAt,
        string $institutionUserId,
    ): VendorCalendarEntry {
        $prebook = VendorCalendarEntry::create([
            'vendor_id' => $vendorId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'prebook_institution_user_id' => $institutionUserId,
            'prebook_at' => now(),
        ]);

        ExpirePrebookJob::dispatch($prebook->id)
            ->delay(now()->plus(minutes: self::PREBOOK_DURATION_MINUTES));

        return $prebook;
    }

    public function getExpiresAt(VendorCalendarEntry $prebook): Carbon
    {
        return $prebook->prebook_at->addMinutes(self::PREBOOK_DURATION_MINUTES);
    }

    /**
     * Called when the user cancels or the reaction timer fires.
     */
    public function expire(string $prebookId): void
    {
        VendorCalendarEntry::where('id', $prebookId)
            ->whereNotNull('prebook_institution_user_id')
            ->whereNull('assignment_id')
            ->delete();
    }

    /**
     * Called when an order is confirmed — converts a prebook into an assignment booking.
     */
    public function convert(VendorCalendarEntry $prebook, Assignment $assignment): void
    {
        $prebook->update([
            'assignment_id' => $assignment->id,
            'prebook_institution_user_id' => null,
            'prebook_at' => null,
        ]);
    }
}
