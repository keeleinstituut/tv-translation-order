<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_main_languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')
                ->constrained('cached_institutions')->cascadeOnDelete();
            $table->foreignUuid('language_id')
                ->constrained('cached_classifier_values');
            $table->timestampsTz();

            $table->unique(['institution_id', 'language_id']);
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_main_languages');
    }
};
