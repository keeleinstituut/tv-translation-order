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
        Schema::create('cat_tool_tms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sub_project_id')
                ->references('id')
                ->on('sub_projects');
            $table->string('tm_id');
            $table->boolean('is_writable');
            $table->boolean('is_readable');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_tool_tms');
    }
};
