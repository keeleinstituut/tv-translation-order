<?php

use App\Enums\VolumeUnits;
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
            $table->enum('unit_type', ['CHARACTER', 'WORD', 'PAGE', 'MINUTE', 'HOUR']);
            $table->unsignedDecimal('unit_quantity');
            $table->unsignedDecimal('unit_fee')->nullable();
            $table->foreignUuid('cat_tool_job_id')->nullable()->constrained('cat_tool_jobs');
            $table->json('custom_volume_analysis')->default('{}');
            $table->json('custom_discounts')->default('{}');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volumes');
    }
};
