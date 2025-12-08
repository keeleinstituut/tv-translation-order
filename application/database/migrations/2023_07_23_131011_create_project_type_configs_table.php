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
        Schema::create('project_type_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('type_classifier_value_id')
                ->references('id')
                ->on('cached_classifier_values');
            $table->string('workflow_process_definition_id');
            $table->json('features');
            $table->timestampsTz();
            $table->unique(['type_classifier_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_type_configs');
    }
};
