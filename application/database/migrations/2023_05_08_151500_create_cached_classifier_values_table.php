<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use SyncTools\Database\Helpers\BaseCachedEntityTableMigration;

return new class extends BaseCachedEntityTableMigration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cached_classifier_values', function (Blueprint $table) {
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
        Schema::dropIfExists('cached_classifier_values');
    }
};
