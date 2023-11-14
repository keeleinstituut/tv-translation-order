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
        Schema::table('sub_projects', function (Blueprint $table) {
            $table->enum('status', [
                'NEW',
                'REGISTERED',
                'CANCELLED',
                'TASKS_SUBMITTED_TO_VENDORS',
                'TASKS_IN_PROGRESS',
                'TASKS_COMPLETED',
                'COMPLETED'
            ])->default('NEW');

            $table->foreignUuid('active_job_definition_id')->nullable()
                ->references('id')
                ->on('job_definitions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_projects', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('active_job_definition_id');
        });
    }
};
