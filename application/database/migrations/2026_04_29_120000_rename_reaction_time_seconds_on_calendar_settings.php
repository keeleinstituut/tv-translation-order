<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            $table->renameColumn('reaction_time_seconds', 'reaction_time_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            $table->renameColumn('reaction_time_minutes', 'reaction_time_seconds');
        });
    }
};
