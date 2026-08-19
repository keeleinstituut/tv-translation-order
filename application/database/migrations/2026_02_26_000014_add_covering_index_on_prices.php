<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * The index is needed for composing of materialized view
         * @see application/database/views/v_vendor_language_coverage.sql
         */
        DB::statement('
            CREATE INDEX idx_prices_mv_covering
            ON prices (vendor_id, skill_id, deleted_at)
            INCLUDE (id, dst_lang_classifier_value_id)
            WHERE deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_prices_mv_covering');
    }
};
