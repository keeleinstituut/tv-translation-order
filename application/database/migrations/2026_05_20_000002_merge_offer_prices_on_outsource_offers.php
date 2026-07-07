<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsource_offers', function (Blueprint $table) {
            $table->decimal('price', 10, 3)->nullable()->after('calculated_price');
        });

        DB::table('outsource_offers')->update([
            'price' => DB::raw('COALESCE(proposed_price, calculated_price)'),
        ]);

        Schema::table('outsource_offers', function (Blueprint $table) {
            $table->dropColumn(['calculated_price', 'proposed_price']);
        });
    }

    public function down(): void
    {
        Schema::table('outsource_offers', function (Blueprint $table) {
            $table->decimal('calculated_price', 10, 3)->nullable()->after('price');
            $table->decimal('proposed_price', 10, 3)->nullable()->after('calculated_price');
        });

        DB::table('outsource_offers')->update([
            'proposed_price' => DB::raw('price'),
        ]);

        Schema::table('outsource_offers', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
