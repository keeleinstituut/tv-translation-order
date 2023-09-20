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
        Schema::table('cat_tool_jobs', function (Blueprint $table) {
            $table->addColumn('enum', 'volume_unit_type', ['allowed' => ['CHARACTERS', 'WORDS', 'PAGES', 'MINUTES', 'HOURS']])
                ->default('WORDS');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cat_tool_jobs', function (Blueprint $table) {
            $table->dropColumn('volume_unit_type');
        });
    }
};
