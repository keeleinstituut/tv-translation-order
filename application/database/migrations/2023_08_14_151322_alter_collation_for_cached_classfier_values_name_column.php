<?php


use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cached_classifier_values ALTER COLUMN name TYPE VARCHAR(255) COLLATE "et-EE-x-icu"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE cached_classifier_values ALTER COLUMN name TYPE VARCHAR(255) COLLATE "default"');
    }
};
