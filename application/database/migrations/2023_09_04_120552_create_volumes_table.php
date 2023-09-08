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
        Schema::create('volumes', function (Blueprint $table) {
            $table->uuid('id');
            $table->foreignUuid('assignment_id')->constrained('assignments');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('cat_chunk_identifier')->nullable();

            $table->string('unit_type');
            $table->unsignedDecimal('unit_quantity');
            $table->unsignedDecimal('unit_fee');
        });

        DB::statement(<<<EOF
            ALTER TABLE "volumes"
            ADD CONSTRAINT "volumes_unit_type_check"
            CHECK ("unit_type" IN ('CHARACTER', 'WORD', 'PAGE', 'MINUTE', 'HOUR'))
        EOF);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volumes');
    }
};
