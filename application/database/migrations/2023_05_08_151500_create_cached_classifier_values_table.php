<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->getSchemaBuilder()->create('cached_classifier_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('type');
            $table->string('value');
            $table->string('name');
            $table->json('meta')->nullable();
            $table->timestampTz('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->getSchemaBuilder()->dropIfExists('cached_classifier_values');
    }

    private function getSchemaBuilder(): Builder
    {
        return Schema::connection('entity-cache-pgsql');
    }
};
