<?php

namespace App\Jobs;

use App\Models\VendorCalendarEntry;
use App\Services\Calendar\VendorReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpirePrebookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $prebookInstitutionUserId) {}

    public function handle(VendorReservationService $vendorReservation): void
    {
        $vendorReservation->releasePrebook($this->prebookInstitutionUserId);
    }
}
