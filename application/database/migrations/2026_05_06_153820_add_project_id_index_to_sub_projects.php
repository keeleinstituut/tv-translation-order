<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_projects', function (Blueprint $table) {
            $table->index('project_id', 'sub_projects_project_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sub_projects', function (Blueprint $table) {
            $table->dropIndex('sub_projects_project_id_idx');
        });
    }
};
