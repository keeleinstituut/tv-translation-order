<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropUnique(['vendor_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id']);
        });

        DB::statement(<<<'EOT'
            CREATE UNIQUE INDEX prices_skill_vendor_language_pair_unique ON prices (vendor_id, src_lang_classifier_value_id, dst_lang_classifier_value_id, skill_id)
            WHERE deleted_at IS NULL
        EOT);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<'EOT'
            DROP INDEX prices_skill_vendor_language_pair_unique
        EOT);

        Schema::table('prices', function (Blueprint $table) {
            $table->unique(['vendor_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id']);
        });
    }
};
