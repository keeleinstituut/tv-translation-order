<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')
                ->unique()->constrained('cached_institutions')->cascadeOnDelete();
            $table->unsignedSmallInteger('reaction_time_seconds')->default(30);
            $table->foreignUuid('default_project_type_id')
                ->constrained('cached_classifier_values');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_settings');
    }
};
