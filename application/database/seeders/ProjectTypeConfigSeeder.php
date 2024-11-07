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
                    'workflow_process_definition_id' => 'interpretation-sub-project',
                    'cat_tool_enabled' => false
                ],
                'POST_TRANSLATION' => [
                    'workflow_process_definition_id' => 'post-translation-sub-project',
                    'cat_tool_enabled' => false
                ],
                'SYNCHRONOUS_TRANSLATION' => [
                    'workflow_process_definition_id' => 'simultaneous-interpretation-sub-project',
                    'cat_tool_enabled' => false
                ],
                'SIGN_LANGUAGE' => [
                    'workflow_process_definition_id' => 'sign-language-sub-project',
                    'cat_tool_enabled' => false
                ],
                'CAT_TRANSLATION_REVIEW' => [
                    'workflow_process_definition_id' => 'cat-translation-review-sub-project',
                    'cat_tool_enabled' => true
                ],
                'CAT_TRANSLATION' => [
                    'workflow_process_definition_id' => 'cat-translation-sub-project',
                    'cat_tool_enabled' => true
                ],
                'TRANSLATION_REVIEW' => [
                    'workflow_process_definition_id' => 'translation-review-sub-project',
                    'cat_tool_enabled' => false
                ],
                'TRANSLATION' => [
                    'workflow_process_definition_id' => 'translation-sub-project',
                    'cat_tool_enabled' => false
                ],
                'EDITING_REVIEW' => [
                    'workflow_process_definition_id' => 'edit-review-sub-project',
                    'cat_tool_enabled' => false
                ],
                'EDITING' => [
                    'workflow_process_definition_id' => 'edit-sub-project',
                    'cat_tool_enabled' => false
                ],
                'EDITED_TRANSLATION_REVIEW' => [
                    'workflow_process_definition_id' => 'cat-edited-translation-review-sub-project',
                    'cat_tool_enabled' => true
                ],
                'EDITED_TRANSLATION' => [
                    'workflow_process_definition_id' => 'cat-edited-translation-sub-project',
                    'cat_tool_enabled' => true
                ],
                'CAT_TRANSLATION_EDITING_REVIEW' => [
                    'workflow_process_definition_id' => 'cat-translation-edit-review-sub-project',
                    'cat_tool_enabled' => true
                ],
                'CAT_TRANSLATION_EDITING' => [
                    'workflow_process_definition_id' => 'cat-translation-edit-sub-project',
                    'cat_tool_enabled' => true
                ],
                'TRANSLATION_EDITING_REVIEW' => [
                    'workflow_process_definition_id' => 'translation-edit-review-sub-project',
                    'cat_tool_enabled' => false
                ],
                'TRANSLATION_EDITING' => [
                    'workflow_process_definition_id' => 'translation-edit-sub-project',
                    'cat_tool_enabled' => false
                ],
                'MANUSCRIPT_TRANSLATION_REVIEW' => [
                    'workflow_process_definition_id' => 'manuscript-translation-review-sub-project',
                    'cat_tool_enabled' => false
                ],
                'MANUSCRIPT_TRANSLATION' => [
                    'workflow_process_definition_id' => 'manuscript-translation-sub-project',
                    'cat_tool_enabled' => false
                ],
                'TERMINOLOGY_WORK' => [
                    'workflow_process_definition_id' => 'terminology-sub-project',
                    'cat_tool_enabled' => false
                ],
                'TERMINOLOGY_WORK_REVIEW' => [
                    'workflow_process_definition_id' => 'terminology-review-sub-project',
                    'cat_tool_enabled' => false
                ],
                'SWORN_CAT_TRANSLATION_REVIEW' => [
                    'workflow_process_definition_id' => 'cat-sworn-translation-review-sub-project',
                    'cat_tool_enabled' => true
                ],
                'SWORN_CAT_TRANSLATION' => [
                    'workflow_process_definition_id' => 'cat-sworn-translation-sub-project',
                    'cat_tool_enabled' => true
                ],
                'SWORN_TRANSLATION_REVIEW' => [
                    'workflow_process_definition_id' => 'sworn-translation-review-sub-project',
                    'cat_tool_enabled' => false
                ],
                'SWORN_TRANSLATION' => [
                    'workflow_process_definition_id' => 'sworn-translation-sub-project',
                    'cat_tool_enabled' => false
                ]
        ];
    }
}
