<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->string('price_mode')->nullable()->after('fixed_price');
        });

        DB::table('outsource_requests')->update([
            'price_mode' => DB::raw("CASE WHEN fixed_price IS NOT NULL THEN 'FIXED_PRICE' ELSE 'PRICELIST_BASED' END"),
        ]);

        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->string('price_mode')->nullable(false)->change();
        });

        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->renameColumn('fixed_price', 'price');
        });

        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->dropColumn('include_price');
        });
    }

    public function down(): void
    {
        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->boolean('include_price')->default(true)->after('price');
        });

        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->renameColumn('price', 'fixed_price');
        });

        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->dropColumn('price_mode');
        });
    }
};
