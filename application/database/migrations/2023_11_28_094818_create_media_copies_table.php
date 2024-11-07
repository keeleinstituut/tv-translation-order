<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_copies', function (Blueprint $table) {
            $table->foreignUuid('source_media_id')->constrained('media', 'uuid')->onDelete('CASCADE');
            $table->foreignUuid('copy_media_id')->constrained('media', 'uuid')->onDelete('CASCADE');
            $table->timestampsTz();
            $table->unique(['source_media_id', 'copy_media_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_copies');
    }
};
