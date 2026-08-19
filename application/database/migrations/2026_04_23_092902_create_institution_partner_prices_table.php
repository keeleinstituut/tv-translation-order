<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_partner_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_partner_id')->constrained('institution_partners')->cascadeOnDelete();
            $table->foreignUuid('src_lang_classifier_value_id')->constrained('cached_classifier_values');
            $table->foreignUuid('dst_lang_classifier_value_id')->constrained('cached_classifier_values');
            $table->foreignUuid('skill_id')->constrained('skills');
            $table->decimal('character_fee', 10, 3)->unsigned()->default(0);
            $table->decimal('word_fee', 10, 3)->unsigned()->default(0);
            $table->decimal('page_fee', 10, 3)->unsigned()->default(0);
            $table->decimal('minute_fee', 10, 3)->unsigned()->default(0);
            $table->decimal('hour_fee', 10, 3)->unsigned()->default(0);
            $table->decimal('minimal_fee', 10, 3)->unsigned()->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX inst_partner_prices_lang_pair_skill_unique
            ON institution_partner_prices (institution_partner_id, src_lang_classifier_value_id, dst_lang_classifier_value_id, skill_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_partner_prices');
    }
};
