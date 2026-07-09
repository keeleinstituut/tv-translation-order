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
            $this->getTranslationDomainTagNames()->map(fn (string $name) => [
                'id' => Str::orderedUuid(),
                'name' => $name,
                'type' => TagType::TranslationDomain->value,
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
            ->whereIn('name', $this->getTranslationDomainTagNames()->toArray())
            ->where('type', TagType::TranslationDomain->value)
            ->whereNull('institution_id')
            ->delete();
    }

    private function getTranslationDomainTagNames(): Collection
    {
        return collect([
            'Haridus',
            'Teadus',
            'Arhiivindus',
            'Noorte- ja keelepoliitika',
            'Õiguspoliitika',
            'Kriminaalpoliitika',
            'Seadusetõlked',
            'Justiitshalduspoliitika',
            'Eelarvepoliitika',
            'Maksu- ja tollipoliitika',
            'Riiklik statistika',
            'Riigiraamatupidamine',
            'Finants- ja kindlustuspoliitika',
            'Kinnisvara- ja osaluspoliitika',
            'Avalik kord ja sisejulgeolek',
            'Kriisireguleerimine ja pästetööd',
            'Piirivalve',
            'Kodakondsuse, rände ja identiteedihaldus',
            'Rahvastiku- ja perepoliitika',
        ]);
    }
};
