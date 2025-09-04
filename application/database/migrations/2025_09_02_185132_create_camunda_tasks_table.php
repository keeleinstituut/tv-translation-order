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
        Schema::create('camunda_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('query_id');

            // Subset of Camunda's task main fields
            $table->uuid('task_id');
            $table->string('task_name');
            $table->timestampTz('task_created');
            $table->uuid('task_execution_id');
            $table->string('task_process_definition_id');
            $table->uuid('task_process_instance_id');
            $table->string('task_task_definition_key');
            $table->uuid('task_assignee')->nullable();

            // Task variables
            $table->uuid("var_project_id")->nullable(); //  => "9fae63fe-1844-44db-9f32-0b851f872b2b"
            $table->uuid("var_sub_project_id")->nullable(); //  => "9fae63fe-1844-44db-9f32-0b851f872b2b"
            $table->uuid("var_institution_id"); // => "9ed6450c-21ad-4c99-aaa1-1105cdf0d8c8"
            $table->uuid("var_assignment_id")->nullable(); // => "9fae63fe-24c9-4c1e-9549-8be94eac44ee"
            $table->uuid("var_source_language_classifier_value_id"); // => "0563b379-4e5c-42c3-9d6a-954755066561"
            $table->uuid("var_destination_language_classifier_value_id"); // => "44229dc1-5a07-411c-aecf-ca522d749c51"
            $table->uuid("var_type_classifier_value_id"); // => "59e7d3b2-3908-46ec-bb25-0078fbe05ff7"
            $table->timestampTz("var_deadline_at"); // => "2025-08-31T20:59:59.000+0000"
            $table->string("var_task_type"); // => "DEFAULT"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camunda_tasks');
    }
};
