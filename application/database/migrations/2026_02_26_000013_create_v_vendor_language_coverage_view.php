<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW v_vendor_language_coverage AS
            SELECT
                p.id AS price_id,
                p.vendor_id,
                p.dst_lang_classifier_value_id AS language_id,
                p.skill_id AS skill_id,
                s.code AS skill_code,
                (ciu.institution->>'id')::uuid AS institution_id,
                v.institution_user_id,
                (v.company_name IS NULL OR v.company_name = '') AS is_internal
            FROM prices p
            JOIN vendors v ON v.id = p.vendor_id
            JOIN cached_institution_users ciu ON ciu.id = v.institution_user_id
            JOIN skills s ON s.id = p.skill_id
            WHERE p.deleted_at IS NULL
              AND v.deleted_at IS NULL
              AND ciu.deleted_at IS NULL
            WITH NO DATA
        SQL);
        DB::statement('CREATE UNIQUE INDEX ON v_vendor_language_coverage (price_id)');
        DB::statement('CREATE INDEX ON v_vendor_language_coverage (skill_code, institution_id)');
        DB::statement('CREATE INDEX ON v_vendor_language_coverage (skill_id, institution_id)');
        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS v_vendor_language_coverage');
    }
};
