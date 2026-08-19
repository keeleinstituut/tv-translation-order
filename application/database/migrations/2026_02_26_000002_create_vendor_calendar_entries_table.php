<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_calendar_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->foreignUuid('assignment_id')
                ->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignUuid('prebook_institution_user_id')
                ->nullable()->constrained('cached_institution_users')->nullOnDelete();
            $table->timestampTz('prebook_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignUuid('vendor_calendar_import_id')
                ->nullable()->constrained('vendor_calendar_imports')->cascadeOnDelete();
            $table->uuid('institution_user_vacation_id')->nullable();
            $table->uuid('institution_vacation_id')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['vendor_id', 'start_at', 'end_at']);
            $table->index('assignment_id');
            $table->index(['prebook_institution_user_id', 'prebook_at']);
        });

        DB::statement('
            CREATE INDEX idx_vc_active_vendor_range ON vendor_calendar_entries (vendor_id, start_at, end_at)
            WHERE deleted_at IS NULL
        ');
        DB::statement('
            CREATE INDEX idx_vc_prebook_expiry ON vendor_calendar_entries (prebook_at)
            WHERE prebook_institution_user_id IS NOT NULL
              AND assignment_id IS NULL
              AND deleted_at IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX idx_vc_assignment_active ON vendor_calendar_entries (assignment_id)
            WHERE assignment_id IS NOT NULL AND deleted_at IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX idx_vc_user_vacation ON vendor_calendar_entries (vendor_id, institution_user_vacation_id)
            WHERE institution_user_vacation_id IS NOT NULL AND deleted_at IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX idx_vc_institution_vacation ON vendor_calendar_entries (vendor_id, institution_vacation_id)
            WHERE institution_vacation_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_calendar_entries');
    }
};
