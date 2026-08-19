<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\ClassifierValueType;
use App\Enums\ProjectStatus;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionSetting;
use App\Models\Project;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotifyProjectsPendingAutoAcceptanceTest extends TestCase
{
    private Institution $institution;

    private string $verbalTypeId;

    private string $nonVerbalTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $this->institution = Institution::factory()->create();
        $this->verbalTypeId = ClassifierValue::where('type', ClassifierValueType::ProjectType)
            ->where('value', 'ORAL_TRANSLATION')->firstOrFail()->id;
        $this->nonVerbalTypeId = ClassifierValue::where('type', ClassifierValueType::ProjectType)
            ->where('value', 'TRANSLATION')->firstOrFail()->id;
    }

    public function test_stamps_verbal_project_past_threshold(): void
    {
        $this->createSetting(['verbal_auto_acceptance_threshold_days' => 5]);
        $project = $this->createSubmittedProject($this->verbalTypeId, Carbon::now()->subDays(6));

        $this->artisan('app:notify-projects-pending-auto-acceptance')->assertSuccessful();

        $this->assertNotNull($project->refresh()->auto_acceptance_notification_sent_at);
    }

    public function test_stamps_non_verbal_project_using_non_verbal_threshold(): void
    {
        $this->createSetting(['non_verbal_auto_acceptance_threshold_days' => 10]);
        $project = $this->createSubmittedProject($this->nonVerbalTypeId, Carbon::now()->subDays(11));

        $this->artisan('app:notify-projects-pending-auto-acceptance')->assertSuccessful();

        $this->assertNotNull($project->refresh()->auto_acceptance_notification_sent_at);
    }

    public function test_skips_project_within_threshold(): void
    {
        $this->createSetting(['verbal_auto_acceptance_threshold_days' => 5]);
        $project = $this->createSubmittedProject($this->verbalTypeId, Carbon::now()->subDays(2));

        $this->artisan('app:notify-projects-pending-auto-acceptance')->assertSuccessful();

        $this->assertNull($project->refresh()->auto_acceptance_notification_sent_at);
    }

    public function test_skips_when_category_disabled(): void
    {
        $this->createSetting([
            'verbal_auto_acceptance_threshold_days' => null,
            'non_verbal_auto_acceptance_threshold_days' => 10,
        ]);
        $project = $this->createSubmittedProject($this->verbalTypeId, Carbon::now()->subDays(30));

        $this->artisan('app:notify-projects-pending-auto-acceptance')->assertSuccessful();

        $this->assertNull($project->refresh()->auto_acceptance_notification_sent_at);
    }

    public function test_skips_when_institution_has_no_settings(): void
    {
        $project = $this->createSubmittedProject($this->verbalTypeId, Carbon::now()->subDays(30));

        $this->artisan('app:notify-projects-pending-auto-acceptance')->assertSuccessful();

        $this->assertNull($project->refresh()->auto_acceptance_notification_sent_at);
    }

    public function test_skips_already_notified_project(): void
    {
        $this->createSetting(['verbal_auto_acceptance_threshold_days' => 5]);
        $alreadyNotifiedAt = Carbon::now()->subDays(3);
        $project = $this->createSubmittedProject($this->verbalTypeId, Carbon::now()->subDays(30));
        $project->auto_acceptance_notification_sent_at = $alreadyNotifiedAt;
        $project->saveQuietly();

        $this->artisan('app:notify-projects-pending-auto-acceptance')->assertSuccessful();

        $this->assertEquals(
            $alreadyNotifiedAt->toDateTimeString(),
            $project->refresh()->auto_acceptance_notification_sent_at->toDateTimeString()
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createSetting(array $attributes): void
    {
        InstitutionSetting::create([
            'institution_id' => $this->institution->id,
            'reaction_time_minutes' => 30,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            ...$attributes,
        ]);
    }

    private function createSubmittedProject(string $typeId, Carbon $submittedAt): Project
    {
        $project = Project::factory()->create([
            'ext_id' => fake()->uuid(),
            'institution_id' => $this->institution->id,
            'type_classifier_value_id' => $typeId,
            'status' => ProjectStatus::SubmittedToClient,
            'submitted_to_client_review_at' => $submittedAt,
        ]);

        return $project;
    }
}
