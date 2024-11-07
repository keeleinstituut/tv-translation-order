<?php

namespace Database\Seeders;

use App\Enums\JobKey;
use App\Models\JobDefinition;
use App\Models\ProjectTypeConfig;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class JobDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skillsMap = Skill::all(['id', 'name'])->keyBy(fn($data) => $this->normalizeSkillName($data['name']));

        ProjectTypeConfig::query()->each(function (ProjectTypeConfig $projectTypeConfig) use ($skillsMap) {
            $jobsDefinitions = self::getData()[$projectTypeConfig->typeClassifierValue->value] ?? [];
            foreach ($jobsDefinitions as $idx => $jobDefinitionAttributes) {
                $jobDefinitionAttributes['sequence'] = $idx;

                if (isset($jobDefinitionAttributes['skill'])) {
                    $normalizedSkillName = $this->normalizeSkillName($jobDefinitionAttributes['skill']);

                    if ($skillsMap->has($normalizedSkillName)) {
                        $jobDefinitionAttributes['skill_id'] = $skillsMap->get($normalizedSkillName)->id;
                    }

                    unset($jobDefinitionAttributes['skill']);
                }

                JobDefinition::updateOrCreate([
                    'project_type_config_id' => $projectTypeConfig->id,
                    'job_key' => $jobDefinitionAttributes['job_key'],
                ], $jobDefinitionAttributes);
            }
        });
    }

    private static function getData(): array
    {
        return [
            'ORAL_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Suuline tõlge',
                    'job_short_name' => 'Suuline tõlge',
                    'skill' => 'Suuline tõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'POST_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Järeltõlge ilma CAT tööriistata',
                    'job_short_name' => 'Järeltõlge',
                    'skill' => 'Järeltõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'SYNCHRONOUS_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Sünkroontõlge',
                    'job_short_name' => 'Sünkroontõlge',
                    'skill' => 'Sünkroontõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'SIGN_LANGUAGE' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Viipekeel',
                    'job_short_name' => 'Viipekeel',
                    'skill' => 'Viipekeel',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'CAT_TRANSLATION_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine sisemise CAT tööriistaga',
                    'job_short_name' => 'Tõlkimine(CAT)',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'CAT_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine sisemise CAT tööriistaga',
                    'job_short_name' => 'Tõlkimine(CAT)',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
            ],
            'TRANSLATION_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine ilma sisemise CAT tööriistata',
                    'job_short_name' => 'Tõlkimine',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine ilma sisemise CAT tööriistata',
                    'job_short_name' => 'Tõlkimine',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'EDITING_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_REVISION,
                    'job_name' => 'Toimetamine välises süsteemis',
                    'job_short_name' => 'Toimetamine',
                    'skill' => 'Toimetamine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'EDITING' => [
                [
                    'job_key' => JobKey::JOB_REVISION,
                    'job_name' => 'Toimetamine välises süsteemis',
                    'job_short_name' => 'Toimetamine',
                    'skill' => 'Toimetamine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'EDITED_TRANSLATION_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine sisemises CAT tööriistas koos toimetamisega',
                    'job_short_name' => 'Toimetatud tõlge(CAT)',
                    'skill' => 'Tõlkimine+Toimetamine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'EDITED_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine sisemises CAT tööriistas koos toimetamisega',
                    'job_short_name' => 'Toimetatud tõlge(CAT)',
                    'skill' => 'Tõlkimine+toimetamine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
            ],
            'CAT_TRANSLATION_EDITING_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine sisemise CAT tööriistaga',
                    'job_short_name' => 'Tõlkimine(CAT)',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
                [
                    'job_key' => JobKey::JOB_REVISION,
                    'job_name' => 'Toimetamine välises süsteemis',
                    'job_short_name' => 'Toimetamine',
                    'skill' => 'Toimetamine',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'CAT_TRANSLATION_EDITING' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine sisemise CAT tööriistaga',
                    'job_short_name' => 'Tõlkimine(CAT)',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
                [
                    'job_key' => JobKey::JOB_REVISION,
                    'job_name' => 'Toimetamine välises süsteemis',
                    'job_short_name' => 'Toimetamine',
                    'skill' => 'Toimetamine',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'TRANSLATION_EDITING_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine ilma sisemise CAT tööriistata',
                    'job_short_name' => 'Tõlkimine',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_REVISION,
                    'job_name' => 'Toimetamine välises süsteemis',
                    'job_short_name' => 'Toimetamine',
                    'skill' => 'Toimetamine',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'TRANSLATION_EDITING' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Tõlkimine ilma sisemise CAT tööriistata',
                    'job_short_name' => 'Tõlkimine',
                    'skill' => 'Tõlkimine',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_REVISION,
                    'job_name' => 'Toimetamine välises süsteemis',
                    'job_short_name' => 'Toimetamine',
                    'skill' => 'Toimetamine',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'MANUSCRIPT_TRANSLATION_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Käsikirjaline tõlge ilma sisemise CAT tööriistata',
                    'job_short_name' => 'Käsikirjaline tõlge',
                    'skill' => 'Käsikirjaline tõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'MANUSCRIPT_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Käsikirjaline tõlge ilma sisemise CAT tööriistata',
                    'job_short_name' => 'Käsikirjaline tõlge',
                    'skill' => 'Käsikirjaline tõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'TERMINOLOGY_WORK' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Terminoloogia töö ilma CAT tööriistata',
                    'job_short_name' => 'Terminoloogia töö',
                    'skill' => 'Terminoloogia töö',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'TERMINOLOGY_WORK_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Terminoloogia töö ilma CAT tööriistata',
                    'job_short_name' => 'Terminoloogia töö',
                    'skill' => 'Terminoloogia töö',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'SWORN_CAT_TRANSLATION_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Vandetõlge sisemises CAT tööriistas',
                    'job_short_name' => 'Vandetõlge(CAT)',
                    'skill' => 'Vandetõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'SWORN_CAT_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Vandetõlge sisemises CAT tööriistas',
                    'job_short_name' => 'Vandetõlge(CAT)',
                    'skill' => 'Vandetõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => true,
                ],
            ],
            'SWORN_TRANSLATION_REVIEW' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Vandetõlge ilma CAT tööriistata',
                    'job_short_name' => 'Vandetõlge',
                    'skill' => 'Vandetõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
                [
                    'job_key' => JobKey::JOB_OVERVIEW,
                    'job_name' => 'Lõpetatuks märkimine tõlkekorraldaja poolt / väljastuseelne ülevaatus',
                    'job_short_name' => 'Ülevaatus',
                    'multi_assignments_enabled' => false,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
            'SWORN_TRANSLATION' => [
                [
                    'job_key' => JobKey::JOB_TRANSLATION,
                    'job_name' => 'Vandetõlge ilma CAT tööriistata',
                    'job_short_name' => 'Vandetõlge',
                    'skill' => 'Vandetõlge',
                    'multi_assignments_enabled' => true,
                    'linking_with_cat_tool_jobs_enabled' => false,
                ],
            ],
        ];
    }

    private function normalizeSkillName(string $name): string
    {
        return preg_replace('/\s+/', '',  mb_strtolower($name));
    }
}
