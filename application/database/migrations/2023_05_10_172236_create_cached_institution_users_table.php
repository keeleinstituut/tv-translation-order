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
        Schema::create('cached_institution_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id');
            $table->uuid('user_id');
            $table->text('forename');
            $table->text('surname');
            $table->text('personal_identification_code');
            $table->string('status', 20);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestampsTz();
            $table->timestampTz('synced_at')->nullable();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cached_institution_users');
    }
};
