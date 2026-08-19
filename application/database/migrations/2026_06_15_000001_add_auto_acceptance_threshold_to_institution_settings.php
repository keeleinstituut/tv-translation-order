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
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('verbal_auto_acceptance_threshold_days')->nullable();
            $table->unsignedSmallInteger('non_verbal_auto_acceptance_threshold_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'verbal_auto_acceptance_threshold_days',
                'non_verbal_auto_acceptance_threshold_days',
            ]);
        });
    }
};
