<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outsource_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('assignments');
            $table->foreignUuid('created_by_institution_user_id')->constrained('cached_institution_users');
            $table->enum('mode', ['CASCADE', 'PARALLEL']);
            $table->unsignedInteger('reaction_time_minutes')->nullable();
            $table->timestampTz('deadline_at')->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('price', 10, 3)->nullable();
            $table->boolean('include_price')->default(true);
            $table->boolean('include_source_files')->default(true);
            $table->enum('status', ['ACTIVE', 'FULFILLED', 'CANCELLED'])->default('ACTIVE');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX outsource_requests_assignment_active_unique
            ON outsource_requests (assignment_id)
            WHERE deleted_at IS NULL AND status = 'ACTIVE'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outsource_requests');
    }
};
