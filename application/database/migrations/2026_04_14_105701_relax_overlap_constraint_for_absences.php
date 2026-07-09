<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vendor_calendar_entries DROP CONSTRAINT IF EXISTS excl_vendor_time_overlap');

        DB::statement('
            ALTER TABLE vendor_calendar_entries
            ADD CONSTRAINT excl_vendor_time_overlap
            EXCLUDE USING gist (
                vendor_id WITH =,
                tstzrange(start_at, end_at) WITH &&
            )
            WHERE (deleted_at IS NULL
              AND institution_user_vacation_id IS NULL
              AND institution_vacation_id IS NULL
              AND absence_creator_institution_user_id IS NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vendor_calendar_entries DROP CONSTRAINT IF EXISTS excl_vendor_time_overlap');

        DB::statement('
            ALTER TABLE vendor_calendar_entries
            ADD CONSTRAINT excl_vendor_time_overlap
            EXCLUDE USING gist (
                vendor_id WITH =,
                tstzrange(start_at, end_at) WITH &&
            )
            WHERE (deleted_at IS NULL
              AND institution_user_vacation_id IS NULL
              AND institution_vacation_id IS NULL)
        ');
    }
};
