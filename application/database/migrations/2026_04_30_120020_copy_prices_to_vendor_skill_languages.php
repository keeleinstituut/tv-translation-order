<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO vendor_skill_languages (
                id, vendor_id, skill_id, src_lang_classifier_value_id, dst_lang_classifier_value_id, created_at, updated_at
            )
            SELECT
                gen_random_uuid(),
                vendor_id,
                skill_id,
                src_lang_classifier_value_id,
                dst_lang_classifier_value_id,
                created_at,
                updated_at
            FROM prices
            WHERE deleted_at IS NULL
            ON CONFLICT DO NOTHING
        SQL);
    }

    public function down(): void
    {
        //
    }
};
