<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_translation_request_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('external_translation_request_id')->constrained('external_translation_requests');
            $table->foreignUuid('institution_id')->constrained('cached_institutions');
            $table->unsignedInteger('position');
            $table->enum('status', ['PENDING', 'NOTIFIED', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'SELECTED'])->default('PENDING');
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->decimal('calculated_price', 10, 3)->nullable();
            $table->decimal('proposed_price', 10, 3)->nullable();
            $table->text('decline_comment')->nullable();
            $table->text('response_comment')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ext_request_recipients_pair_unique
            ON external_translation_request_recipients (external_translation_request_id, institution_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX ext_request_recipients_position_idx
            ON external_translation_request_recipients (external_translation_request_id, position)
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ext_request_recipients_selected_unique
            ON external_translation_request_recipients (external_translation_request_id)
            WHERE status = 'SELECTED' AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('external_translation_request_recipients');
    }
};
