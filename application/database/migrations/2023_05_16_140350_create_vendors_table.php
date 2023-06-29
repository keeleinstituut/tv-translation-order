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
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table
                ->foreignUuid('institution_user_id')
                ->references('id')
                ->on('entity_cache.cached_institution_users')
                ->constrained();

            $table->string('company_name')->nullable();
            $table->timestampsTz();
            $table->unique(['institution_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
