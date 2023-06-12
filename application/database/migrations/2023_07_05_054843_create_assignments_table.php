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
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sub_project_id')
                ->references('id')
                ->on('sub_projects');
            $table->foreignUuid('assigned_vendor_id')
                ->nullable()
                ->references('id')
                ->on('vendors');
            $table->timestampTz('deadline_at')->nullable();
            $table->text('comments')->default("");
            $table->text('assignee_comments')->default("");
            $table->string('feature');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
