<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
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
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

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
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->runCommand();

        $this->assertCount(1, $this->publishedMessages);
    }

    public function test_does_not_send_if_any_assignment_has_vendor(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => Vendor::factory(),
        ]);

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
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_does_not_resend_for_already_notified_assignments(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
            'timeslot_passed_notification_sent_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->runCommand();

        $this->assertCount(0, $this->publishedMessages);
    }

    public function test_sends_one_notification_per_unassigned_assignment(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = $this->createCalendarProject(ProjectStatus::New, Carbon::now()->subHour(), $manager);
        $subProject = $this->createSubProject($project);
        Assignment::factory()->count(3)->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->runCommand();

        $this->assertCount(3, $this->publishedMessages);
    }

    private function runCommand(): void
    {
        $this->publishedMessages = [];

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    private function createSubProject(Project $project): SubProject
    {
        return SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
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
}
