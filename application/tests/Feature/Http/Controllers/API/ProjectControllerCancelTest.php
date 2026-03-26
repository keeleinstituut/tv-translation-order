<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Http\Controllers\API\ProjectController;
use App\Jobs\ProjectDelayedCancelJob;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;
use Tests\AuthHelpers;
use Tests\TestCase;

class ProjectControllerCancelTest extends TestCase
{
    public function test_non_calendar_project_is_cancelled_immediately(): void
    {
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'is_calendar_project' => false,
            'status' => ProjectStatus::New,
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'cancel'], ['id' => $project->id]), [
                'cancellation_reason' => 'No longer needed',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', ProjectStatus::Cancelled->value);
        $this->assertNull($response->json('data.cancellation_pending_at'));
    }

    public function test_calendar_project_cancel_is_delayed_by_default(): void
    {
        Queue::fake();

        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'cancel'], ['id' => $project->id]), [
                'cancellation_reason' => 'Schedule changed',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', ProjectStatus::New->value);
        $this->assertNotNull($response->json('data.cancellation_pending_at'));
        $this->assertNotNull($response->json('data.cancel_at'));

        Queue::assertPushed(ProjectDelayedCancelJob::class, function (ProjectDelayedCancelJob $job) use ($project) {
            return $job->projectId === $project->id;
        });
    }

    public function test_calendar_project_cancel_is_immediate_when_is_delayed_false(): void
    {
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'cancel'], ['id' => $project->id]), [
                'cancellation_reason' => 'Urgent cancel',
                'is_delayed' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', ProjectStatus::Cancelled->value);
        $this->assertNull($response->json('data.cancellation_pending_at'));
    }

    public function test_double_cancel_while_pending_returns_conflict(): void
    {
        Queue::fake();

        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'cancellation_pending_at' => now(),
            'cancellation_reason' => 'First attempt',
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'cancel'], ['id' => $project->id]), [
                'cancellation_reason' => 'Second attempt',
            ]);

        $response->assertStatus(409);
    }

    public function test_decline_cancellation_clears_pending_state(): void
    {
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'cancellation_pending_at' => now(),
            'cancellation_reason' => 'Changed mind',
            'cancellation_comment' => 'Oops',
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'declineCancellation'], ['id' => $project->id]));

        $response->assertOk();
        $response->assertJsonPath('data.status', ProjectStatus::New->value);
        $this->assertNull($response->json('data.cancellation_pending_at'));
        $this->assertNull($response->json('data.cancel_at'));

        $project->refresh();
        $this->assertNull($project->cancellation_pending_at);
        $this->assertNull($project->cancellation_reason);
        $this->assertNull($project->cancellation_comment);
    }

    public function test_decline_cancellation_fails_when_no_pending(): void
    {
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'declineCancellation'], ['id' => $project->id]));

        $response->assertStatus(400);
    }

    public function test_delayed_cancel_job_cancels_project(): void
    {
        // Auth::check() in ProjectObserver uses default 'web' guard which has
        // no 'model' configured — switch to 'api' guard to avoid the error.
        config(['auth.defaults.guard' => 'api']);

        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'cancellation_pending_at' => now(),
            'cancellation_reason' => 'Schedule conflict',
        ]);

        $job = new ProjectDelayedCancelJob($project->id);
        $job->handle();

        $project->refresh();
        $this->assertEquals(ProjectStatus::Cancelled, $project->status);
        $this->assertNull($project->cancellation_pending_at);
    }

    public function test_delayed_cancel_job_is_noop_when_declined(): void
    {
        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'status' => ProjectStatus::New,
            'cancellation_pending_at' => null,
            'cancellation_reason' => null,
        ]);

        $job = new ProjectDelayedCancelJob($project->id);
        $job->handle();

        $project->refresh();
        $this->assertEquals(ProjectStatus::New, $project->status);
    }
}
