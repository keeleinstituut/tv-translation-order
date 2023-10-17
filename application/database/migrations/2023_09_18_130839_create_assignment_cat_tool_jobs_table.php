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
        Schema::create('assignment_cat_tool_jobs', function (Blueprint $table) {
            $table->uuid('id');
            $table->foreignUuid('assignment_id')->constrained('assignments')
                ->onDelete('cascade');
            $table->foreignUuid('cat_tool_job_id')->constrained('cat_tool_jobs')
                ->onDelete('cascade');
            $table->timestampsTz();

            $table->unique(['assignment_id', 'cat_tool_job_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_cat_tool_jobs');
    }
};
