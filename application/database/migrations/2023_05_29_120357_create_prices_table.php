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
        Schema::create('prices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table
                ->foreignUuid('vendor_id')
                ->references('id')
                ->on('vendors')
                ->constrained();

            // $table
            //     ->foreignUuid('skill_classifier_value_id')
            //     ->references('id')
            //     ->on('cached_classifier_values')
            //     ->constrained();

            $table
                ->foreignUuid('src_lang_classifier_value_id')
                ->references('id')
                ->on('entity_cache.cached_classifier_values')
                ->constrained();

            $table
                ->foreignUuid('dst_lang_classifier_value_id')
                ->references('id')
                ->on('entity_cache.cached_classifier_values')
                ->constrained();

            $table->unsignedDecimal('character_fee', 10, 2);
            $table->unsignedDecimal('word_fee', 10, 2);
            $table->unsignedDecimal('page_fee', 10, 2);
            $table->unsignedDecimal('minute_fee', 10, 2);
            $table->unsignedDecimal('hour_fee', 10, 2);
            $table->unsignedDecimal('minimal_fee', 10, 2);

            $table->timestampsTz();

            $table->unique(['vendor_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
