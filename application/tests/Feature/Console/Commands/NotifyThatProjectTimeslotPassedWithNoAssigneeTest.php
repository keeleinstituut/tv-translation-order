<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\JobKey;
use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\JobDefinition;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use SyncTools\AmqpPublisher;
use Tests\TestCase;

class NotifyThatProjectTimeslotPassedWithNoAssigneeTest extends TestCase
{
    /** @var array<int, array{message: array, exchange: string}> */
    private array $publishedMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('amqp.notifications.email_notification_exchange', 'test-notifications');

        $this->publishedMessages = [];

        $this->mock(AmqpPublisher::class, function (MockInterface $mock) {
            $mock->shouldReceive('publish')
                ->andReturnUsing(function (array $message, string $exchange) {
                    $this->publishedMessages[] = ['message' => $message, 'exchange' => $exchange];
                });
            $mock->shouldReceive('setup')->andReturnNull();
        });
    }

    public function test_sends_notification_for_new_calendar_project_with_past_timeslot(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        $assignment = $this->createTranslationAssignment($subProject);

        $this->runCommand();

        $this->assertCount(1, $this->publishedMessages);
        $this->assertEquals(
            'PROJECT_TIMESLOT_PASSED_WITH_NO_ASSIGNEE',
            $this->publishedMessages[0]['message']['type']
        );

        $assignment->refresh();
        $this->assertNotNull($assignment->timeslot_passed_notification_sent_at);
    }

    public function test_sends_notification_for_registered_status(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::Registered, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        $this->createTranslationAssignment($subProject);

        $this->runCommand();

        $this->assertCount(1, $this->publishedMessages);
    }

    public function test_does_not_send_if_translation_assignment_has_vendor(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        $this->createTranslationAssignment($subProject, assignedVendorId: Vendor::factory()->create()->id);

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_ignores_non_translation_assignments(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        $this->createAssignmentWithJobKey($subProject, JobKey::JOB_OVERVIEW);

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_does_not_send_for_non_calendar_project(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        Project::factory()->create([
            'is_calendar_project' => false,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_does_not_send_if_event_end_at_is_in_the_future(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->addHour(), $manager);
        $subProject = $this->createSubProject($project);
        $this->createTranslationAssignment($subProject);

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_does_not_resend_for_already_notified_assignments(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        $this->createTranslationAssignment($subProject, notifiedAt: Carbon::now()->subMinutes(30));

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_sends_one_notification_per_unassigned_translation_assignment(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        $this->createTranslationAssignment($subProject);
        $this->createTranslationAssignment($subProject);
        $this->createTranslationAssignment($subProject);

        $this->runCommand();

        $this->assertCount(3, $this->publishedMessages);
    }

    private function runCommand(): void
    {
        $this->publishedMessages = [];

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    private function createCalendarProject(
        ProjectStatus $status,
        Carbon $eventEndAt,
        InstitutionUser $manager,
    ): Project {
        return Project::factory()->create([
            'is_calendar_project' => true,
            'status' => $status,
            'event_end_at' => $eventEndAt,
            'manager_institution_user_id' => $manager->id,
        ]);
    }

    private function createSubProject(Project $project): SubProject
    {
        return SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
    }

    private function createTranslationAssignment(
        SubProject $subProject,
        ?string $assignedVendorId = null,
        ?Carbon $notifiedAt = null,
    ): Assignment {
        return $this->createAssignmentWithJobKey(
            $subProject,
            JobKey::JOB_TRANSLATION,
            $assignedVendorId,
            $notifiedAt,
        );
    }

    private function createAssignmentWithJobKey(
        SubProject $subProject,
        JobKey $jobKey,
        ?string $assignedVendorId = null,
        ?Carbon $notifiedAt = null,
    ): Assignment {
        $jobDefinition = JobDefinition::create([
            'project_type_config_id' => ProjectTypeConfig::factory()->create()->id,
            'job_key' => $jobKey,
            'job_short_name' => $jobKey->value,
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 1,
        ]);

        return Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'job_definition_id' => $jobDefinition->id,
            'assigned_vendor_id' => $assignedVendorId,
            'timeslot_passed_notification_sent_at' => $notifiedAt,
        ]);
    }
}
