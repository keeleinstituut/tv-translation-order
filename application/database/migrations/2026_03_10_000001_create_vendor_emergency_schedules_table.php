<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_emergency_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')
                ->constrained('vendors')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['vendor_id', 'start_date', 'end_date']);
        });

        DB::statement('
            CREATE INDEX vendor_emergency_schedules_active_vendor_dates
            ON vendor_emergency_schedules (vendor_id, start_date, end_date)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_emergency_schedules');
    }
};
