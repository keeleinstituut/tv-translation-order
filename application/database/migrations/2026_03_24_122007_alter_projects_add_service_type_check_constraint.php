<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE projects ADD CONSTRAINT project_service_type_check CHECK (service_type IN ('ON_SITE', 'REMOTE'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE projects DROP CONSTRAINT project_service_type_check');
    }
};
