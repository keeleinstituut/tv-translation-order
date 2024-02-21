<?php

use Illuminate\Database\Migrations\Migration;
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
        Schema::table('cached_institution_users', function (Blueprint $table) {
            $table->jsonb('vacations')->default('{}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cached_institution_users', function (Blueprint $table) {
            $table->dropColumn('vacations');
        });
    }
};
