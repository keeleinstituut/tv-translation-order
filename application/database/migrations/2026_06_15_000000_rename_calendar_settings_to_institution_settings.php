<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('calendar_settings', 'institution_settings');
    }

    public function down(): void
    {
        Schema::rename('institution_settings', 'calendar_settings');
    }
};
