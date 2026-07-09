<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX outsource_requests_assignment_not_deleted_unique');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX outsource_requests_assignment_not_deleted_unique
            ON outsource_requests (assignment_id)
            WHERE deleted_at IS NULL AND status != 'CANCELLED'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX outsource_requests_assignment_not_deleted_unique');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX outsource_requests_assignment_not_deleted_unique
            ON outsource_requests (assignment_id)
            WHERE deleted_at IS NULL
        SQL);
    }
};
