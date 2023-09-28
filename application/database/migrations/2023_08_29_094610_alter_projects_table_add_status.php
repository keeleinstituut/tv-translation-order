<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status');
        });

        $validStatusValues = self::getValidProjectStatuses()->map(fn ($name) => "'$name'")->join(', ');

        DB::statement(
            'ALTER TABLE projects '.
            'ADD CONSTRAINT projects_status_check '.
            "CHECK (status IN ($validStatusValues))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    private static function getValidProjectStatuses(): Collection
    {
        return collect([
            'NEW',
            'REGISTERED',
            'CANCELLED',
            'SUBMITTED_TO_CLIENT',
            'REJECTED',
            'CORRECTED',
            'ACCEPTED',
        ]);
    }
};
