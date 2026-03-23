<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_user_pinned_languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_user_id')
                ->constrained('cached_institution_users')->cascadeOnDelete();
            $table->foreignUuid('institution_main_language_id')
                ->constrained('institution_main_languages')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['institution_user_id', 'institution_main_language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_user_pinned_languages');
    }
};
