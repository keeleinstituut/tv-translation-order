<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_partners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained('cached_institutions');
            $table->foreignUuid('partner_institution_id')->constrained('cached_institutions');
            $table->decimal('discount_percentage_101', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_repetitions', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_100', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_95_99', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_85_94', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_75_84', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_50_74', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_0_49', 5)->unsigned()->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX institution_partners_pair_unique
            ON institution_partners (institution_id, partner_institution_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE institution_partners
            ADD CONSTRAINT institution_partners_no_self_partner_chk
            CHECK (institution_id <> partner_institution_id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_partners');
    }
};
