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
        Schema::create('job_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_type_config_id')->constrained('project_type_configs')
                ->onDelete('cascade');
            $table->enum('job_key', ['job_translation', 'job_revision', 'job_overview']);
            $table->foreignUuid('skill_id')->nullable()->constrained('skills');
            $table->boolean('multi_assignments_enabled');
            $table->boolean('linking_with_cat_tool_jobs_enabled');
            $table->unsignedInteger('sequence');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_definitions');
    }
};
