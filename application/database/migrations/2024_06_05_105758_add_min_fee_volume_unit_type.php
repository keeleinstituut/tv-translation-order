<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use \Doctrine\DBAL\Types\Type;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE volumes '.
            'DROP CONSTRAINT volumes_unit_type_check'
        );

        DB::statement(
            'ALTER TABLE volumes '.
            'ADD CONSTRAINT volumes_unit_type_check '.
            "CHECK (unit_type IN ('CHARACTERS', 'WORDS', 'PAGES', 'MINUTES', 'HOURS', 'MIN_FEE'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE volumes '.
            'DROP CONSTRAINT volumes_unit_type_check'
        );

        DB::statement(
            'ALTER TABLE volumes '.
            'ADD CONSTRAINT volumes_unit_type_check '.
            "CHECK (unit_type IN ('CHARACTERS', 'WORDS', 'PAGES', 'MINUTES', 'HOURS'))"
        );
    }
};
