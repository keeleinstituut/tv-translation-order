<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')->cascadeOnDelete();
            $table->text('comment');
            $table->foreignUuid('institution_user_id')
                ->constrained('cached_institution_users');
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_comments');
    }
};
