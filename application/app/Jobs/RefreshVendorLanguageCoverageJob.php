<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RefreshVendorLanguageCoverageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public string $queue = 'calendar-refresh';

    public int $tries = 3;

    public int $backoff = 5;
    
    public int $uniqueFor = 10;

    public function handle(): void
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY v_vendor_language_coverage');
    }
}
