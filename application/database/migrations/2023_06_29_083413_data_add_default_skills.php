<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('skills')->insert(
            $this->getVendorSkillTagsNames()->map(fn (string $name) => [
                'id' => Str::orderedUuid(),
                'name' => $name,
                'created_at' => DB::raw('NOW()'),
                'updated_at' => DB::raw('NOW()'),
            ])->toArray()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('skills')
            ->whereIn('name', $this->getVendorSkillTagsNames()->toArray())
            ->delete();
    }

    private function getVendorSkillTagsNames(): Collection
    {
        return collect([
            'Suuline tõlge',
            'Sünkroontõlge',
            'Järeltõlge',
            'Viipekeel',
            'Salvestise tõlge',
            'Tõlkimine',
            'Toimetamine',
            'Tõlkimine + Toimetamine',
            'Käsikirjaline tõlge',
            'Infovahetus',
            'Terminoloogia töö',
            'Vandetõlge',
        ]);
    }
};
