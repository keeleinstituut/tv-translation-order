<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ProjectTypeClassifierValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ClassifierValue::getModel()
            ->setConnection(Config::get('pgsql-connection.sync.name'))
            ->insert(
                collect(static::getData())
                    ->mapSpread(fn (string $name, string $value) => [
                        'id' => DB::raw('gen_random_uuid()'),
                        'name' => $name,
                        'value' => $value,
                        'type' => ClassifierValueType::ProjectType->value,
                    ])
                    ->all()
            );
    }

    private static function getData(): array
    {
        return [
            ['Suuline tõlge', 'S'],
            ['Järeltõlge', 'JÄ'],
            ['Sünkroontõlge', 'SÜ'],
            ['Viipekeel', 'VK'],
            ['Tõlkimine (CAT), Ülevaatus', 'T'],
            ['Tõlkimine (CAT)', 'T'],
            ['Tõlkimine, Ülevaatus', 'T'],
            ['Tõlkimine', 'T'],
            ['Toimetamine, Ülevaatus', 'TO'],
            ['Toimetamine', 'TO'],
            ['Toimetatud tõlge, Ülevaatus', 'TO'],
            ['Toimetatud tõlge', 'TT'],
            ['Tõlkimine (CAT), Toimetamine, Ülevaatus', 'TT'],
            ['Tõlkimine (CAT), Toimetamine', 'TT'],
            ['Tõlkimine, Toimetamine, Ülevaatus', 'TT'],
            ['Tõlkimine, Toimetamine', 'TT'],
            ['Käsikirjaline tõlge, Ülevaatus', 'KT'],
            ['Käsikirjaline tõlge', 'KT'],
            ['Terminoloogia töö', 'TR'],
            ['Vandetõlge (CAT), ülevaatus', 'VT'],
            ['Vandetõlge (CAT)', 'VT'],
            ['Vandetõlge, Ülevaatus', 'VT'],
            ['Vandetõlge', 'VT'],
        ];
    }
}
