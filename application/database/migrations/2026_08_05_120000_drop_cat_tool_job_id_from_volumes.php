<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->dropForeign(['cat_tool_job_id']);
            $table->dropColumn('cat_tool_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->foreignUuid('cat_tool_job_id')->nullable()
                ->constrained('cat_tool_jobs')->onDelete('cascade');
        });
    }
};
