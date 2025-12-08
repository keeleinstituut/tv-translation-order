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
        Schema::table('vendors', function (Blueprint $table) {
            $table->decimal('discount_percentage_101', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_repetitions', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_100', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_95_99', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_85_94', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_75_84', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_50_74', 5, 2)->unsigned()->nullable();
            $table->decimal('discount_percentage_0_49', 5, 2)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('discount_percentage_101');
            $table->dropColumn('discount_percentage_repetitions');
            $table->dropColumn('discount_percentage_100');
            $table->dropColumn('discount_percentage_95_99');
            $table->dropColumn('discount_percentage_85_94');
            $table->dropColumn('discount_percentage_75_84');
            $table->dropColumn('discount_percentage_50_74');
            $table->dropColumn('discount_percentage_0_49');
        });
    }
};
