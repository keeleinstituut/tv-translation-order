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
        Schema::create('cat_tool_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sub_project_id')
                ->references('id')
                ->on('sub_projects');
            $table->string('ext_id')->unique();
            $table->string('name');
            $table->string('translate_url');
            $table->string('revise_url')->nullable();
            $table->string('progress_percentage')->default(0);
            $table->json('volume_analysis')->default('{}');
            $table->json('metadata')->default('{}');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_tool_jobs');
    }
};
