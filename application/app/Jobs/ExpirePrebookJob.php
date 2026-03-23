<?php

namespace App\Jobs;

use App\Models\VendorCalendarEntry;
use App\Services\Calendar\PrebookService;
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

    public function __construct(public readonly string $prebookId) {}

    public function handle(PrebookService $prebookService): void
    {
        $prebook = VendorCalendarEntry::find($this->prebookId);

        // Idempotent: already converted to an assignment or manually expired.
        if (! $prebook || $prebook->assignment_id || $prebook->trashed()) {
            return;
        }

        $prebookService->expire($this->prebookId);
    }
}
