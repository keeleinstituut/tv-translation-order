<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outsource_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outsource_request_id')->constrained('outsource_requests');
            $table->foreignUuid('institution_id')->constrained('cached_institutions');
            $table->unsignedInteger('position');
            $table->enum('status', [
                'REQUEST_PENDING', 'REQUEST_SENT',
                'REQUEST_ACCEPTED', 'REQUEST_DECLINED', 'REQUEST_EXPIRED',
                'OFFER_ACCEPTED', 'OFFER_DECLINED',
            ])->default('REQUEST_PENDING');
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
            CREATE UNIQUE INDEX outsource_offers_pair_unique
            ON outsource_offers (outsource_request_id, institution_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX outsource_offers_position_idx
            ON outsource_offers (outsource_request_id, position)
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX outsource_offers_selected_unique
            ON outsource_offers (outsource_request_id)
            WHERE status = 'OFFER_ACCEPTED' AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outsource_offers');
    }
};
