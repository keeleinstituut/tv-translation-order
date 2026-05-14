<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->renameColumn('price', 'fixed_price');
        });
    }

    public function down(): void
    {
        Schema::table('outsource_requests', function (Blueprint $table) {
            $table->renameColumn('fixed_price', 'price');
        });
    }
};
