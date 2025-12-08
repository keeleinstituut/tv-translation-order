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
        Schema::table('prices', function (Blueprint $table) {
            $table->unsignedDecimal('character_fee', places: 3)->nullable(false)->change();
            $table->unsignedDecimal('word_fee', places: 3)->nullable(false)->change();
            $table->unsignedDecimal('page_fee', places: 3)->nullable(false)->change();
            $table->unsignedDecimal('minute_fee', places: 3)->nullable(false)->change();
            $table->unsignedDecimal('hour_fee', places: 3)->nullable(false)->change();
            $table->unsignedDecimal('minimal_fee', places: 3)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->unsignedDecimal('character_fee', 10, 2)->nullable(false)->change();
            $table->unsignedDecimal('word_fee', 10, 2)->nullable(false)->change();
            $table->unsignedDecimal('page_fee', 10, 2)->nullable(false)->change();
            $table->unsignedDecimal('minute_fee', 10, 2)->nullable(false)->change();
            $table->unsignedDecimal('hour_fee', 10, 2)->nullable(false)->change();
            $table->unsignedDecimal('minimal_fee', 10, 2)->nullable(false)->change();
        });
    }
};
