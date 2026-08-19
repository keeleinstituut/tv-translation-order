<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_skill_languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained('skills');
            $table->foreignUuid('src_lang_classifier_value_id')->constrained('cached_classifier_values');
            $table->foreignUuid('dst_lang_classifier_value_id')->constrained('cached_classifier_values');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX vendor_skill_languages_unique
            ON vendor_skill_languages (vendor_id, skill_id, src_lang_classifier_value_id, dst_lang_classifier_value_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX vendor_skill_languages_lang_skill_idx
            ON vendor_skill_languages (dst_lang_classifier_value_id, skill_id)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX vendor_skill_languages_skill_vendor_idx
            ON vendor_skill_languages (skill_id, vendor_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_skill_languages');
    }
};
