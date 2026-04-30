<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS v_vendor_language_coverage');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW v_vendor_language_coverage AS
            SELECT
                vsl.id AS price_id,
                vsl.vendor_id,
                vsl.dst_lang_classifier_value_id AS language_id,
                vsl.skill_id AS skill_id,
                s.code AS skill_code,
                (ciu.institution->>'id')::uuid AS institution_id,
                v.institution_user_id,
                (v.company_name IS NULL OR v.company_name = '') AS is_internal
            FROM vendor_skill_languages vsl
            JOIN vendors v ON v.id = vsl.vendor_id
            JOIN cached_institution_users ciu ON ciu.id = v.institution_user_id
            JOIN skills s ON s.id = vsl.skill_id
            WHERE vsl.deleted_at IS NULL
              AND v.deleted_at IS NULL
              AND ciu.deleted_at IS NULL
            WITH NO DATA
        SQL);
        DB::statement('CREATE UNIQUE INDEX ON v_vendor_language_coverage (price_id)');
        DB::statement('CREATE INDEX ON v_vendor_language_coverage (skill_code, institution_id)');
        DB::statement('CREATE INDEX ON v_vendor_language_coverage (skill_id, institution_id)');
        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');
    }
};
