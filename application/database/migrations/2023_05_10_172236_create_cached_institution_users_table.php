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
        $this->getSchemaBuilder()->create('cached_institution_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('forename');
            $table->text('surname');
            $table->text('personal_identification_code');
            $table->string('status', 20);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestampsTz();
            $table->timestampTz('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->getSchemaBuilder()->dropIfExists('cached_institution_users');
    }

    private function getSchemaBuilder(): Builder
    {
        return Schema::connection('entity-cache-pgsql');
    }
};
