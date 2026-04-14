<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('buffer_before_minutes')->default(30)->change();
            $table->unsignedSmallInteger('buffer_after_minutes')->default(30)->change();
        });

        DB::table('calendar_settings')
            ->where('buffer_before_minutes', 0)
            ->where('buffer_after_minutes', 0)
            ->update([
                'buffer_before_minutes' => 30,
                'buffer_after_minutes' => 30,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0)->change();
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0)->change();
        });
    }
};
