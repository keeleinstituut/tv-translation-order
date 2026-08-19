<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cached_institution_users', function (Blueprint $table) {
            $table->string('worktime_timezone', 50)->nullable();
            $table->time('monday_worktime_start')->nullable();
            $table->time('monday_worktime_end')->nullable();
            $table->time('tuesday_worktime_start')->nullable();
            $table->time('tuesday_worktime_end')->nullable();
            $table->time('wednesday_worktime_start')->nullable();
            $table->time('wednesday_worktime_end')->nullable();
            $table->time('thursday_worktime_start')->nullable();
            $table->time('thursday_worktime_end')->nullable();
            $table->time('friday_worktime_start')->nullable();
            $table->time('friday_worktime_end')->nullable();
            $table->time('saturday_worktime_start')->nullable();
            $table->time('saturday_worktime_end')->nullable();
            $table->time('sunday_worktime_start')->nullable();
            $table->time('sunday_worktime_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cached_institution_users', function (Blueprint $table) {
            $table->dropColumn([
                'worktime_timezone',
                'monday_worktime_start', 'monday_worktime_end',
                'tuesday_worktime_start', 'tuesday_worktime_end',
                'wednesday_worktime_start', 'wednesday_worktime_end',
                'thursday_worktime_start', 'thursday_worktime_end',
                'friday_worktime_start', 'friday_worktime_end',
                'saturday_worktime_start', 'saturday_worktime_end',
                'sunday_worktime_start', 'sunday_worktime_end',
            ]);
        });
    }
};
