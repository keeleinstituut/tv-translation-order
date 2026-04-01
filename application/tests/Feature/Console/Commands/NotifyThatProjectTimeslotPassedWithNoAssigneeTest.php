<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Tests\TestCase;

class NotifyThatProjectTimeslotPassedWithNoAssigneeTest extends TestCase
{
    private MockInterface $notificationPublisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationPublisher = $this->mock(NotificationPublisher::class, function (MockInterface $mock) {
            $mock->shouldReceive('publishEmailNotification')->byDefault();
        });
    }

    public function test_sends_notification_for_new_calendar_project_with_past_timeslot(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->notificationPublisher
            ->shouldReceive('publishEmailNotification')
            ->once()
            ->withArgs(function ($message) {
                return $message->notificationType === NotificationType::ProjectTimeslotPassedWithNoAssignee;
            });

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);

        $assignment->refresh();
        $this->assertNotNull($assignment->timeslot_passed_notification_sent_at);
    }

    public function test_sends_notification_for_registered_status(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::Registered,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->notificationPublisher
            ->shouldReceive('publishEmailNotification')
            ->once();

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    public function test_does_not_send_if_any_assignment_has_vendor(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => Vendor::factory(),
        ]);

        $this->notificationPublisher
            ->shouldNotReceive('publishEmailNotification');

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    public function test_does_not_send_for_non_calendar_project(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => false,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->notificationPublisher
            ->shouldNotReceive('publishEmailNotification');

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    public function test_does_not_send_if_event_end_at_is_in_the_future(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->addHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->notificationPublisher
            ->shouldNotReceive('publishEmailNotification');

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    public function test_does_not_resend_for_already_notified_assignments(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
            'timeslot_passed_notification_sent_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->notificationPublisher
            ->shouldNotReceive('publishEmailNotification');

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }

    public function test_sends_one_notification_per_unassigned_assignment(): void
    {
        $manager = InstitutionUser::factory()->create(['email' => 'manager@test.com']);
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'event_end_at' => Carbon::now()->subHour(),
            'manager_institution_user_id' => $manager->id,
        ]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        Assignment::factory()->count(3)->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $this->notificationPublisher
            ->shouldReceive('publishEmailNotification')
            ->times(3);

        $this->artisan('app:notify-that-project-timeslot-passed-with-no-assignee')
            ->assertExitCode(0);
    }
}
