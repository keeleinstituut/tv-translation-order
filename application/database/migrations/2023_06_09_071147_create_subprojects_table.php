<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sub_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ext_id')->unique();
            $table->foreignUuid('project_id')
                ->references('id')
                ->on('projects');

            $table->string('file_collection');
            $table->string('file_collection_final');
            $table->string('matecat_job_id')->nullable();
            $table->string('workflow_ref')->nullable();
            $table->foreignUuid('source_language_classifier_value_id')
                ->references('id')
                ->on('entity_cache.cached_classifier_values');
            $table->foreignUuid('destination_language_classifier_value_id')
                ->references('id')
                ->on('entity_cache.cached_classifier_values');
            $table->json('cat_metadata')->default("{}");

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subprojects');
    }
};
