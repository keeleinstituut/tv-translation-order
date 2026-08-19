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
        Schema::create('institution_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->unique()
                ->constrained('cached_institutions');
            $table->decimal('discount_percentage_101', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_repetitions', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_100', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_95_99', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_85_94', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_75_84', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_50_74', 5)->unsigned()->nullable();
            $table->decimal('discount_percentage_0_49', 5)->unsigned()->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institution_discounts');
    }
};
