<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
        });

        foreach ($this->getSkillCodes() as $name => $code) {
            DB::table('skills')->where('name', $name)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    /** @return array<string, string> */
    private function getSkillCodes(): array
    {
        return [
            'Suuline tõlge' => 'ORAL_INTERPRETATION',
            'Sünkroontõlge' => 'SIMULTANEOUS_INTERPRETATION',
            'Järeltõlge' => 'CONSECUTIVE_INTERPRETATION',
            'Viipekeel' => 'SIGN_LANGUAGE',
            'Salvestise tõlge' => 'RECORDING_TRANSLATION',
            'Tõlkimine' => 'TRANSLATION',
            'Toimetamine' => 'EDITING',
            'Tõlkimine + Toimetamine' => 'TRANSLATION_AND_EDITING',
            'Käsikirjaline tõlge' => 'HANDWRITTEN_TRANSLATION',
            'Infovahetus' => 'INFORMATION_EXCHANGE',
            'Terminoloogia töö' => 'TERMINOLOGY_WORK',
            'Vandetõlge' => 'SWORN_TRANSLATION',
        ];
    }
};
