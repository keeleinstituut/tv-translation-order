<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TranslationDomainClassifierValueSeeder extends Seeder
{
    public function run(): void
    {
        ClassifierValue::getModel()
            ->insert(
                collect(static::getData())
                    ->map(fn (array $classifierValueData) => [
                        ...$classifierValueData,
                        'id' => DB::raw('gen_random_uuid()'),
                        'type' => ClassifierValueType::TranslationDomain->value,
                    ])
                    ->all()
            );
    }

    private static function getData(): array
    {
        return [
            [
                'name' => 'Haridus',
                'value' => 'HAR',
            ],
            [
                'name' => 'Teadus',
                'value' => 'TEA',
            ],
            [
                'name' => 'Arhiivindus',
                'value' => 'ARH',
            ],
            [
                'name' => 'Noorte- ja keelepoliitika',
                'value' => 'NKP',
            ],
            [
                'name' => 'Õiguspoliitika',
                'value' => 'ÕIP',
            ],
            [
                'name' => 'Kriminaalpoliitika',
                'value' => 'KRP',
            ],
            [
                'name' => 'Seadusetõlked',
                'value' => 'SET',
            ],
            [
                'name' => 'Justiitshalduspoliitika',
                'value' => 'JHP',
            ],
            [
                'name' => 'Eelarvepoliitika',
                'value' => 'EAP',
            ],
            [
                'name' => 'Maksu- ja tollipoliitika',
                'value' => 'MTP',
            ],
            [
                'name' => 'Riiklik statistika',
                'value' => 'RST',
            ],
            [
                'name' => 'Riigiraamatupidamine',
                'value' => 'RRP',
            ],
            [
                'name' => 'Finants- ja kindlustuspoliitika',
                'value' => 'FKP',
            ],
            [
                'name' => 'Kinnisvara- ja osaluspoliitika',
                'value' => 'KOP',
            ],
            [
                'name' => 'Avalik kord ja sisejulgeolek',
                'value' => 'ASP',
            ],
            [
                'name' => 'Kriisireguleerimine ja pästetööd',
                'value' => 'KPT',
            ],
            [
                'name' => 'Piirivalve',
                'value' => 'PRV',
            ],
            [
                'name' => 'Kodakondsuse, rände ja identiteedihaldus',
                'value' => 'KRI',
            ],
            [
                'name' => 'Rahvastiku- ja perepoliitika',
                'value' => 'RPP',
            ],
        ];
    }
}
