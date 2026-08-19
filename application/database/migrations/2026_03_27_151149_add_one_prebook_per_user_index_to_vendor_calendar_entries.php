<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX idx_vce_one_prebook_per_user
            ON vendor_calendar_entries (prebook_institution_user_id)
            WHERE prebook_institution_user_id IS NOT NULL
              AND assignment_id IS NULL
              AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_vce_one_prebook_per_user');
    }
};
