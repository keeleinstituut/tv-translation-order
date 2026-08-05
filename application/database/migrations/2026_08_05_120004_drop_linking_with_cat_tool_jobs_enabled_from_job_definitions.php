<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_definitions', function (Blueprint $table) {
            $table->dropColumn('linking_with_cat_tool_jobs_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('job_definitions', function (Blueprint $table) {
            $table->boolean('linking_with_cat_tool_jobs_enabled')->default(false);
        });
    }
};
