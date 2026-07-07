<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\ClassifierValueType;
use App\Enums\JobKey;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTypeCode;
use App\Enums\SubProjectStatus;
use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\JobDefinition;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use App\Models\Tag;
use App\Models\Vendor;
use App\Models\Volume;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StatisticsSeeder extends Seeder
{
    public const INSTITUTION_ID = '00000000-0000-4000-8000-000000000001';

    public const CREATED_2024_01 = '2024-01-15 10:00:00';
    public const ACCEPTED_2024_02 = '2024-02-20 10:00:00';
    public const CREATED_2025_03 = '2025-03-10 10:00:00';

    public const VOLUME_QUANTITY = [
        'WORDS' => 100,
        'CHARACTERS' => 500,
        'PAGES' => 2,
        'MINUTES' => 30,
        'HOURS' => 1,
        'MIN_FEE' => 1,
    ];

    public function run(): void
    {
        $institution = Institution::query()->find(self::INSTITUTION_ID)
            ?? Institution::factory()->create(['id' => self::INSTITUTION_ID]);

        $oralType = ClassifierValue::factory()
            ->withType(ClassifierValueType::ProjectType)
            ->create(['value' => ProjectTypeCode::OralTranslation->value]);

        $writtenType = ClassifierValue::factory()
            ->withType(ClassifierValueType::ProjectType)
            ->create(['value' => 'TRANSLATION']);

        $sourceLanguage = ClassifierValue::factory()
            ->withType(ClassifierValueType::Language)
            ->create(['value' => 'ET']);

        $destinationLanguage = ClassifierValue::factory()
            ->withType(ClassifierValueType::Language)
            ->create(['value' => 'EN']);

        $tag = Tag::factory()->typeOrder()->create(['institution_id' => $institution->id]);

        $projectTypeConfig = ProjectTypeConfig::factory()->create([
            'type_classifier_value_id' => $oralType->id,
        ]);
        $translationJob = JobDefinition::create([
            'project_type_config_id' => $projectTypeConfig->id,
            'job_key' => JobKey::JOB_TRANSLATION,
            'job_name' => 'Translation',
            'job_short_name' => 'Tõlkimine',
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 0,
        ]);
        $revisionJob = JobDefinition::create([
            'project_type_config_id' => $projectTypeConfig->id,
            'job_key' => JobKey::JOB_REVISION,
            'job_name' => 'Revision',
            'job_short_name' => 'Toimetamine',
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 1,
        ]);

        $overviewJob = JobDefinition::create([
            'project_type_config_id' => $projectTypeConfig->id,
            'job_key' => JobKey::JOB_OVERVIEW,
            'job_name' => 'Overview',
            'job_short_name' => 'Ülevaatus',
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 2,
        ]);

        $vendor = Vendor::factory()->create();

        // Project 1 — oral (verbal), accepted. Created 2024-01, accepted 2024-02.
        $subProject1 = $this->createProject(
            typeClassifierValueId: $oralType->id,
            status: ProjectStatus::Accepted,
            price: 100,
            createdAt: self::CREATED_2024_01,
            acceptedAt: self::ACCEPTED_2024_02,
            tag: $tag,
            sourceLanguageId: $sourceLanguage->id,
            destinationLanguageId: $destinationLanguage->id,
            subProjectStatus: SubProjectStatus::Completed,
            subProjectPrice: 100,
        );
        $this->createAssignmentWithVolumes($subProject1, $translationJob, $vendor, AssignmentStatus::Done, 50, 60, self::CREATED_2024_01, self::ACCEPTED_2024_02);
        $this->createAssignmentWithVolumes($subProject1, $revisionJob, $vendor, AssignmentStatus::InProgress, 40, 45, self::CREATED_2024_01, null);
        $this->createAssignmentWithVolumes($subProject1, $overviewJob, $vendor, AssignmentStatus::Done, 20, 25, self::CREATED_2024_01, self::ACCEPTED_2024_02);

        // Project 2 — written (non-verbal), not accepted, no tag. Created 2024-01.
        $subProject2 = $this->createProject(
            typeClassifierValueId: $writtenType->id,
            status: ProjectStatus::New,
            price: 200,
            createdAt: self::CREATED_2024_01,
            acceptedAt: null,
            tag: null,
            sourceLanguageId: $sourceLanguage->id,
            destinationLanguageId: $destinationLanguage->id,
            subProjectStatus: SubProjectStatus::New,
            subProjectPrice: 200,
        );
        $this->createAssignmentWithVolumes($subProject2, $translationJob, null, AssignmentStatus::New, 80, 90, self::CREATED_2024_01, null);

        // Project 3 — oral (verbal), accepted. Created 2025-03, accepted 2025-03.
        $subProject3 = $this->createProject(
            typeClassifierValueId: $oralType->id,
            status: ProjectStatus::Registered,
            price: 300,
            createdAt: self::CREATED_2025_03,
            acceptedAt: self::CREATED_2025_03,
            tag: $tag,
            sourceLanguageId: $sourceLanguage->id,
            destinationLanguageId: $destinationLanguage->id,
            subProjectStatus: SubProjectStatus::TasksInProgress,
            subProjectPrice: 300,
        );
        $this->createAssignmentWithVolumes($subProject3, $translationJob, $vendor, AssignmentStatus::Done, 120, 130, self::CREATED_2025_03, self::CREATED_2025_03);
    }

    private function createProject(
        string $typeClassifierValueId,
        ProjectStatus $status,
        float $price,
        string $createdAt,
        ?string $acceptedAt,
        ?Tag $tag,
        string $sourceLanguageId,
        string $destinationLanguageId,
        SubProjectStatus $subProjectStatus,
        float $subProjectPrice,
    ): SubProject {
        $project = Project::factory()->create([
            'institution_id' => self::INSTITUTION_ID,
            'type_classifier_value_id' => $typeClassifierValueId,
            'status' => $status,
            'price' => $price,
            'created_at' => $createdAt,
            'accepted_at' => $acceptedAt,
        ]);

        if ($tag !== null) {
            $project->tags()->attach($tag->id);
        }

        return SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguageId,
            'destination_language_classifier_value_id' => $destinationLanguageId,
            'status' => $subProjectStatus,
            'price' => $subProjectPrice,
            'created_at' => $createdAt,
        ]);
    }

    private function createAssignmentWithVolumes(
        SubProject $subProject,
        JobDefinition $jobDefinition,
        ?Vendor $vendor,
        AssignmentStatus $status,
        float $price,
        float $priceWithoutDiscount,
        string $createdAt,
        ?string $completedAt,
    ): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'job_definition_id' => $jobDefinition->id,
            'assigned_vendor_id' => $vendor?->id,
            'status' => $status,
            'price' => $price,
            //'price_without_discount' => $priceWithoutDiscount,
            'created_at' => $createdAt,
            'completed_at' => $completedAt,
        ]);

        $assignment->completed_at = $completedAt;
        $assignment->saveQuietly();

        Volume::withoutEvents(function () use ($assignment) {
            foreach (VolumeUnits::cases() as $unit) {
                $assignment->volumes()->create([
                    'id' => (string) Str::uuid(),
                    'unit_type' => $unit,
                    'unit_quantity' => self::VOLUME_QUANTITY[$unit->value],
                ]);
            }
        });
    }
}
