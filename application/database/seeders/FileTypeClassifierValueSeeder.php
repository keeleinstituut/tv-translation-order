<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class FileTypeClassifierValueSeeder extends Seeder
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
                    ->map(fn (array $classifierValueData) => [
                        ...$classifierValueData,
                        'id' => DB::raw('gen_random_uuid()'),
                        'type' => ClassifierValueType::FileType->value,
                    ])
                    ->all()
            );
    }

    private static function getData(): array
    {
        return [
            [
                'name' => 'Stiilijuhis',
                'value' => 'SJ',
            ],
            [
                'name' => 'Terminibaas',
                'value' => 'TB',
            ],
            [
                'name' => 'Abifail',
                'value' => 'AF',
            ],
            [
                'name' => 'Tõlkemälu',
                'value' => 'TM',
            ],
        ];
    }
}
