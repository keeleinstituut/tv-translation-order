<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(file_get_contents(database_path('views/v_vendor_language_coverage.sql')));
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
