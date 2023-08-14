<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SyncTools\Database\Helpers\BaseCachedEntityTableMigration;

return new class extends BaseCachedEntityTableMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE entity_cache.cached_classifier_values ALTER COLUMN name TYPE VARCHAR(255) COLLATE "et-EE-x-icu"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE entity_cache.cached_classifier_values ALTER COLUMN name TYPE VARCHAR(255) COLLATE "default"');
    }
};
