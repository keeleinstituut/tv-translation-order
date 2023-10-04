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
        $syncSchema = Config::get('pgsql-connection.sync.properties.schema');
        Schema::create('institution_discounts', function (Blueprint $table) use ($syncSchema) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->unique()
                ->constrained("$syncSchema.cached_institutions");
            $table->unsignedDecimal('discount_percentage_101', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_repetitions', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_100', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_95_99', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_85_94', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_75_84', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_50_74', 5)->nullable();
            $table->unsignedDecimal('discount_percentage_0_49', 5)->nullable();
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
