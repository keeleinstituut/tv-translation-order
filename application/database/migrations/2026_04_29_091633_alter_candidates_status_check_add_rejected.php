<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_status_check");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_status_check CHECK (status IN ('NEW', 'SUBMITTED_TO_VENDOR', 'ACCEPTED', 'DECLINED', 'REJECTED', 'DONE'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_status_check");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_status_check CHECK (status IN ('NEW', 'SUBMITTED_TO_VENDOR', 'ACCEPTED', 'DECLINED', 'DONE'))");
    }
};
