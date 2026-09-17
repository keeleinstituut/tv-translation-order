<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cat_tool_tm_keys');
    }

    public function down(): void
    {
        Schema::create('cat_tool_tm_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sub_project_id')
                ->references('id')
                ->on('sub_projects');
            $table->string('key')->index();
            $table->boolean('is_writable')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->boolean('created_as_empty')->default(false);

            $table->unique(['sub_project_id', 'key']);
        });
    }
};
