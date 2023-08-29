<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
use App\Enums\Feature;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\ProjectTypeConfig;
use Illuminate\Database\Seeder;

class ProjectTypeConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ClassifierValue::where('type', ClassifierValueType::ProjectType)
            ->each(function (ClassifierValue $projectTypeClassifierValue) {
                ProjectTypeConfig::create([
                    'type_classifier_value_id' => $projectTypeClassifierValue->id,
                    'workflow_process_definition_id' => 'Sample-subproject',
                    'features' => Feature::values(),
                    'is_start_date_supported' => in_array($projectTypeClassifierValue->value, ['S', 'JÄ', 'SÜ', 'VK']),
                ]);
            });
    }
}
