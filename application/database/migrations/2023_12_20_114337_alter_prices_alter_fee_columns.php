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
            $table->decimal('character_fee', places: 3)->unsigned()->nullable(false)->change();
            $table->decimal('word_fee', places: 3)->unsigned()->nullable(false)->change();
            $table->decimal('page_fee', places: 3)->unsigned()->nullable(false)->change();
            $table->decimal('minute_fee', places: 3)->unsigned()->nullable(false)->change();
            $table->decimal('hour_fee', places: 3)->unsigned()->nullable(false)->change();
            $table->decimal('minimal_fee', places: 3)->unsigned()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->decimal('character_fee', 10, 2)->unsigned()->nullable(false)->change();
            $table->decimal('word_fee', 10, 2)->unsigned()->nullable(false)->change();
            $table->decimal('page_fee', 10, 2)->unsigned()->nullable(false)->change();
            $table->decimal('minute_fee', 10, 2)->unsigned()->nullable(false)->change();
            $table->decimal('hour_fee', 10, 2)->unsigned()->nullable(false)->change();
            $table->decimal('minimal_fee', 10, 2)->unsigned()->nullable(false)->change();
        });
    }
};
