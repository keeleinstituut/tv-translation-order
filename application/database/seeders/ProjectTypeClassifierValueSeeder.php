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
                    ->mapSpread(fn (string $name, string $value, array $meta) => [
                        'id' => DB::raw('gen_random_uuid()'),
                        'name' => $name,
                        'value' => $value,
                        'meta' => json_encode($meta),
                        'type' => ClassifierValueType::ProjectType->value,
                    ])->all()
            );
    }

    private function getData(): array
    {
        return [
            ['Suuline tõlge', 'ORAL_TRANSLATION', ['code' => 'S']],
            ['Sünkroontõlge', 'SYNCHRONOUS_TRANSLATION', ['code' => 'SÜ']],
            ['Järeltõlge', 'POST_TRANSLATION', ['code' => 'JÄ']],
            ['Viipekeel', 'SIGN_LANGUAGE', ['code' => 'VK']],
            ['Tõlkimine (CAT), Ülevaatus', 'CAT_TRANSLATION_REVIEW', ['code' => 'T']],
            ['Tõlkimine (CAT), Toimetamine, Ülevaatus', 'CAT_TRANSLATION_EDITING_REVIEW', ['code' => 'TT']],
            ['Tõlkimine (CAT), Toimetamine', 'CAT_TRANSLATION_EDITING', ['code' => 'TT']],
            ['Tõlkimine (CAT)', 'CAT_TRANSLATION', ['code' => 'T']],
            ['Toimetatud tõlge', 'EDITED_TRANSLATION', ['code' => 'TT']],
            ['Toimetatud tõlge, Ülevaatus', 'EDITED_TRANSLATION_REVIEW', ['code' => 'TO']],
            ['Tõlkimine, Ülevaatus', 'TRANSLATION_REVIEW', ['code' => 'T']],
            ['Tõlkimine', 'TRANSLATION', ['code' => 'T']],
            ['Tõlkimine, Toimetamine, Ülevaatus', 'TRANSLATION_EDITING_REVIEW', ['code' => 'TT']],
            ['Tõlkimine, Toimetamine', 'TRANSLATION_EDITING', ['code' => 'TT']],
            ['Käsikirjaline tõlge', 'MANUSCRIPT_TRANSLATION', ['code' => 'KT']],
            ['Käsikirjaline tõlge, Ülevaatus', 'MANUSCRIPT_TRANSLATION_REVIEW', ['code' => 'KT']],
            ['Terminoloogia töö', 'TERMINOLOGY_WORK', ['code' => 'TR']],
            ['Terminoloogia töö, Ülevaatus', 'TERMINOLOGY_WORK_REVIEW', ['code' => 'TR']],
            ['Vandetõlge (CAT), ülevaatus', 'SWORN_CAT_TRANSLATION_REVIEW', ['code' => 'VT']],
            ['Vandetõlge (CAT)', 'SWORN_CAT_TRANSLATION', ['code' => 'VT']],
            ['Vandetõlge, Ülevaatus', 'SWORN_TRANSLATION_REVIEW', ['code' => 'VT']],
            ['Vandetõlge', 'SWORN_TRANSLATION', ['code' => 'VT']],
            ['Toimetamine, Ülevaatus', 'EDITING_REVIEW', ['code' => 'TO']],
            ['Toimetamine', 'EDITING', ['code' => 'TO']],
        ];
    }
}
