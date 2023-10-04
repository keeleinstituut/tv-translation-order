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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ext_id')->unique();
            $table->string('reference_number');
            $table->foreignUuid('institution_id')
                ->references('id')
                ->on('entity_cache.cached_institutions');
            $table->foreignUuid('type_classifier_value_id')
                ->references('id')
                ->on('entity_cache.cached_classifier_values');
            $table->text('comments')->default('');
            $table->string('workflow_template_id')->nullable();
            $table->string('workflow_instance_ref')->nullable();
            $table->timestampTz('deadline_at')->nullable();
            $table->timestampsTz();

            // ORDERS
            // ---------------------
            // id
            // generated_identifier
            // Type classifier
            // Domain
            // deadline timestamp
            // Comments
            // reference_number
            // source language
            // destination languages MULTIPLE
            // files MULTIPLE
            // client_id
            // translation_manager_id

            // SUBORDERS
            // ---------------------
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
