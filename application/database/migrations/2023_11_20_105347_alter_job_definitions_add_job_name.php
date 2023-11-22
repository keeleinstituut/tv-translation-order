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
        Schema::table('job_definitions', function (Blueprint $table) {
            $table->string('job_name', 1000)->nullable();
            $table->string('job_short_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_definitions', function (Blueprint $table) {
            $table->dropColumn('job_name');
            $table->dropColumn('job_short_name');
        });
    }
};
