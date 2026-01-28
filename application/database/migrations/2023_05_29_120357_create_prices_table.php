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

            $table
                ->foreignUuid('src_lang_classifier_value_id')
                ->references('id')
                ->on('cached_classifier_values')
                ->constrained();

            $table
                ->foreignUuid('dst_lang_classifier_value_id')
                ->references('id')
                ->on('cached_classifier_values')
                ->constrained();

            $table->decimal('character_fee', 10, 2)->unsigned();
            $table->decimal('word_fee', 10, 2)->unsigned();
            $table->decimal('page_fee', 10, 2)->unsigned();
            $table->decimal('minute_fee', 10, 2)->unsigned();
            $table->decimal('hour_fee', 10, 2)->unsigned();
            $table->decimal('minimal_fee', 10, 2)->unsigned();

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
