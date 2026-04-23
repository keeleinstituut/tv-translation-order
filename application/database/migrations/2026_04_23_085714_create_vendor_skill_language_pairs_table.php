<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_skill_language_pairs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors');
            $table->foreignUuid('src_lang_classifier_value_id')->constrained('cached_classifier_values');
            $table->foreignUuid('dst_lang_classifier_value_id')->constrained('cached_classifier_values');
            $table->foreignUuid('skill_id')->constrained('skills');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX vendor_skill_language_pairs_skill_vendor_lang_pair_unique
            ON vendor_skill_language_pairs (vendor_id, src_lang_classifier_value_id, dst_lang_classifier_value_id, skill_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_skill_language_pairs');
    }
};
