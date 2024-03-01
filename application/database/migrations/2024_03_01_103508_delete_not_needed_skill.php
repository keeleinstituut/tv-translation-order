<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $id = DB::table('skills')->where('name', 'Infovahetus')->pluck('id')[0] ?? null;
        if (filled($id)) {
            // NOTE: this part of migration can't be reverted
            DB::table('prices')->where('skill_id', $id)->delete();

            DB::table('skills')->delete($id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('skills')->insert([
            [
                'id' => Str::orderedUuid(),
                'name' => 'Infovahetus',
                'created_at' => DB::raw('NOW()'),
                'updated_at' => DB::raw('NOW()'),
            ]
        ]);
    }
};
