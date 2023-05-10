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
        $this->getSchemaBuilder()->create('cached_institutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('name');
            $table->string('logo_url')->nullable();
            $table->timestampsTz();
            $table->timestampTz('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->getSchemaBuilder()->dropIfExists('cached_institutions');
    }

    private function getSchemaBuilder(): Builder
    {
        return Schema::connection('entity-cache-pgsql');
    }
};
