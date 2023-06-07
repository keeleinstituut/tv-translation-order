<?php

use App\Enums\TagType;
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
        DB::table('tags')->insert(
            $this->getVendorSkillTagsNames()->map(fn(string $name) => [
                'id' => Str::orderedUuid(),
                'name' => $name,
                'type' => TagType::VendorSkill->value,
                'institution_id' => null,
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
        DB::table('tags')
            ->whereIn('name', $this->getVendorSkillTagsNames()->toArray())
            ->where('type', TagType::VendorSkill->value)
            ->whereNull('institution_id')
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
