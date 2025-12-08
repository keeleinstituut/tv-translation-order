<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cached_institution_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('phone');
            $table->date('deactivation_date')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->jsonb('user');
            $table->jsonb('institution');
            $table->jsonb('department');
            $table->jsonb('roles');
            $table->softDeletesTz();
            $table->timestampTz('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cached_institution_users');
    }
};
