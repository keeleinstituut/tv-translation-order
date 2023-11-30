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
        Schema::table('projects', function (Blueprint $table) {
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('corrected_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
            $table->dropColumn('accepted_at');
            $table->dropColumn('rejected_at');
            $table->dropColumn('corrected_at');
        });
    }
};
