<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            $table->foreignUuid('default_project_type_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            $table->foreignUuid('default_project_type_id')
                ->nullable(false)
                ->change();
        });
    }
};
