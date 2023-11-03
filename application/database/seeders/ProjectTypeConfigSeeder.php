<?php

namespace Database\Seeders;

use App\Enums\ClassifierValueType;
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
                $attributes = self::getData()[$projectTypeClassifierValue->value] ?? [];
                if (empty($attributes)) {
                    return;
                }

                $attributes['features'] = [];
                $attributes['is_start_date_supported'] = in_array(data_get($projectTypeClassifierValue->meta, 'code'), ['S', 'JÄ', 'SÜ', 'VK']);

                ProjectTypeConfig::updateOrCreate(
                    ['type_classifier_value_id' => $projectTypeClassifierValue->id],
                    $attributes
                );
            });
    }

    private static function getData(): array
    {
        return [
            'ORAL_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'POST_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'SYNCHRONOUS_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'SIGN_LANGUAGE' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'CAT_TRANSLATION_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => true,
            ],
            'CAT_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => true,
            ],
            'TRANSLATION_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'EDITING_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'EDITING' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'EDITED_TRANSLATION_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'EDITED_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'CAT_TRANSLATION_EDITING_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => true,
            ],
            'CAT_TRANSLATION_EDITING' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => true,
            ],
            'TRANSLATION_EDITING_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'TRANSLATION_EDITING' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'MANUSCRIPT_TRANSLATION_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'MANUSCRIPT_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'TERMINOLOGY_WORK' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'TERMINOLOGY_WORK_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'SWORN_CAT_TRANSLATION_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => true,
            ],
            'SWORN_CAT_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => true,
            ],
            'SWORN_TRANSLATION_REVIEW' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
            'SWORN_TRANSLATION' => [
                'workflow_process_definition_id' => 'Sample-subproject',
                'cat_tool_enabled' => false,
            ],
        ];
    }
}
