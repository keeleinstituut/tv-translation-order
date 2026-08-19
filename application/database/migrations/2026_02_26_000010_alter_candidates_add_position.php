<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->smallInteger('position')->default(0)->after('assignment_id');
            $table->index(['assignment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['assignment_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
