<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Builder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->getSchemaBuilder()->table('cached_institution_users', function (Blueprint $table) {
            $table->uuid('institution_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->getSchemaBuilder()->table('cached_institution_users', function (Blueprint $table) {
            $table->dropColumn('institution_id');
        });
    }

    private function getSchemaBuilder(): Builder
    {
        return Schema::connection('entity-cache-pgsql');
    }
};
