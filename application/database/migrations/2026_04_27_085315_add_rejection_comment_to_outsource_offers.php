<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsource_offers', function (Blueprint $table): void {
            $table->text('rejection_comment')->nullable()->after('decline_comment');
        });
    }

    public function down(): void
    {
        Schema::table('outsource_offers', function (Blueprint $table): void {
            $table->dropColumn('rejection_comment');
        });
    }
};
