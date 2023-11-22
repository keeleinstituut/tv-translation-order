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
        Schema::table('volumes', function (Blueprint $table) {
            $table->unsignedDecimal('unit_quantity', places: 3)->change();
            $table->unsignedDecimal('unit_fee', places: 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->unsignedDecimal('unit_quantity')->change();
            $table->unsignedDecimal('unit_fee')->nullable()->change();
        });
    }
};
