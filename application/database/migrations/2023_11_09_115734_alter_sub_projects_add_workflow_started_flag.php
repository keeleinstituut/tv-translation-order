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
        Schema::table('sub_projects', function (Blueprint $table) {
            $table->dropColumn('workflow_ref');
            $table->boolean('workflow_started')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_projects', function (Blueprint $table) {
            $table->dropColumn('workflow_started');
            $table->string('workflow_ref')->nullable();
        });
    }
};
