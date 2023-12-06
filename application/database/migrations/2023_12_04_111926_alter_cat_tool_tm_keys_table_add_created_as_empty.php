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
        Schema::table('cat_tool_tm_keys', function (Blueprint $table) {
            $table->boolean('created_as_empty')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cat_tool_tm_keys', function (Blueprint $table) {
            $table->dropColumn('created_as_empty');
        });
    }
};
