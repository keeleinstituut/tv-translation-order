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
        Schema::table('project_type_configs', function (Blueprint $table) {
            $table->boolean('cat_tool_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_type_configs', function (Blueprint $table) {
            $table->dropColumn('cat_tool_enabled');
        });
    }
};
