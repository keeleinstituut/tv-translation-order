<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn('default_project_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->foreignUuid('default_project_type_id')
                ->nullable()
                ->constrained('cached_classifier_values');
        });
    }
};
