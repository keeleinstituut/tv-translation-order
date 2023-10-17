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
        //
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['institution_user_id']);
        });

        DB::statement(<<<'EOT'
            CREATE UNIQUE INDEX vendors_institution_user_id_unique ON vendors (institution_user_id)
            WHERE deleted_at IS NULL
        EOT);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<'EOT'
            DROP INDEX vendors_institution_user_id_unique
        EOT);

        Schema::table('vendors', function (Blueprint $table) {
            $table->unique(['institution_user_id']);
        });
    }
};
