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
        Schema::create('cat_tool_tm_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sub_project_id')
                ->references('id')
                ->on('sub_projects');
            $table->string('key')->index();
            $table->boolean('is_writable');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['sub_project_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_tool_tm_keys');
    }
};
