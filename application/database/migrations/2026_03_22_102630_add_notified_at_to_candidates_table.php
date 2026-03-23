<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->timestampTz('notified_at')->nullable();
        });

        DB::statement("ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_status_check");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_status_check CHECK (status IN ('NEW', 'SUBMITTED_TO_VENDOR', 'ACCEPTED', 'DECLINED', 'DONE'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_status_check");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_status_check CHECK (status IN ('NEW', 'SUBMITTED_TO_VENDOR', 'ACCEPTED', 'DONE'))");

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
