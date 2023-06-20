<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SyncTools\Database\Helpers\BaseCachedEntityTableMigration;

return new class extends BaseCachedEntityTableMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cached_institutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('name');
            $table->string('short_name', 3)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('logo_url')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cached_institutions');
    }
};
