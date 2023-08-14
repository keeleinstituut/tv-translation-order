<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE application.tags ALTER COLUMN name TYPE VARCHAR(50) COLLATE "et-EE-x-icu"');
        DB::statement('ALTER TABLE application.vendors ALTER COLUMN company_name TYPE VARCHAR(255) COLLATE "et-EE-x-icu"');
        DB::statement('ALTER TABLE application.skills ALTER COLUMN name TYPE VARCHAR(255) COLLATE "et-EE-x-icu"');
        DB::statement('ALTER TABLE entity_cache.cached_classifier_values ALTER COLUMN name TYPE VARCHAR(255) COLLATE "et-EE-x-icu"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE application.tags ALTER COLUMN name TYPE VARCHAR(50) COLLATE "default"');
        DB::statement('ALTER TABLE application.vendors ALTER COLUMN company_name TYPE VARCHAR(255) COLLATE "default"');
        DB::statement('ALTER TABLE application.skills ALTER COLUMN name TYPE VARCHAR(255) COLLATE "default"');
        DB::statement('ALTER TABLE entity_cache.cached_classifier_values ALTER COLUMN name TYPE VARCHAR(255) COLLATE "default"');
    }
};
