<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_calendar_entries', function (Blueprint $table) {
            $table->foreignUuid('absence_creator_institution_user_id')
                ->nullable()
                ->constrained('cached_institution_users')
                ->nullOnDelete();
        });

        DB::statement('
            CREATE INDEX vendor_calendar_entries_absences_active
            ON vendor_calendar_entries (vendor_id, start_at)
            WHERE absence_creator_institution_user_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS vendor_calendar_entries_absences_active');

        Schema::table('vendor_calendar_entries', function (Blueprint $table) {
            $table->dropForeign(['absence_creator_institution_user_id']);
            $table->dropColumn('absence_creator_institution_user_id');
        });
    }
};
