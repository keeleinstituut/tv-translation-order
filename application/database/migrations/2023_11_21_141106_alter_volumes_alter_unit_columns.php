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
            $table->decimal('unit_quantity', places: 3)->unsigned()->nullable(false)->change();
            $table->decimal('unit_fee', places: 3)->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->decimal('unit_quantity')->unsigned()->change();
            $table->decimal('unit_fee')->unsigned()->nullable()->change();
        });
    }
};
