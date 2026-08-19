<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\CandidateStatus;
use App\Enums\ClassifierValueType;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Jobs\ProcessCandidatesNotificationCycle;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\AuthHelpers;
use Tests\TestCase;

class WorkflowControllerDeclineTaskTest extends TestCase
{
    private Institution $institution;

    private string $camundaBaseUrl;

    private ClassifierValue $sourceLanguage;

    private ClassifierValue $destinationLanguage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $this->institution = Institution::factory()->create();
        $this->camundaBaseUrl = rtrim(env('CAMUNDA_API_URL', 'http://process-definition'), '/');
        $this->sourceLanguage = ClassifierValue::where('type', ClassifierValueType::Language)
            ->where('value', 'et-EE')
            ->firstOrFail();
        $this->destinationLanguage = ClassifierValue::where('type', ClassifierValueType::Language)
            ->whereNot('value', 'et-EE')
            ->firstOrFail();
    }

    public function test_vendor_can_decline_task_and_candidate_is_marked_declined(): void
    {
        // GIVEN
        Queue::fake();

        $vendorInstitutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser->id,
            'company_name' => fake()->company(),
        ]);

        $project = $this->createProjectWithAssignment();
        $assignment = $project->subProjects->first()->assignments->first();

        $candidate = Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
            'status' => CandidateStatus::SubmittedToVendor,
            'position' => 1,
            'notified_at' => now(),
        ]);

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForDecline($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorInstitutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/workflow/tasks/{$taskId}/decline");

        // THEN
        $response->assertOk();

        $candidate->refresh();
        $this->assertEquals(CandidateStatus::Declined, $candidate->status);
    }

    public function test_decline_returns_400_when_vendor_has_no_pending_proposal(): void
    {
        // GIVEN
        Queue::fake();

        $vendorInstitutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser->id,
            'company_name' => fake()->company(),
        ]);

        $project = $this->createProjectWithAssignment();
        $assignment = $project->subProjects->first()->assignments->first();

        // Candidate exists but already declined
        Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
            'status' => CandidateStatus::Declined,
            'position' => 1,
        ]);

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForDecline($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorInstitutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/workflow/tasks/{$taskId}/decline");

        // THEN
        $response->assertBadRequest();
    }

    public function test_decline_returns_400_when_user_is_not_a_vendor(): void
    {
        // GIVEN
        Queue::fake();

        $nonVendorUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();

        $project = $this->createProjectWithAssignment();
        $assignment = $project->subProjects->first()->assignments->first();

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForDecline($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $nonVendorUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/workflow/tasks/{$taskId}/decline");

        // THEN
        $response->assertBadRequest();
    }

    public function test_decline_triggers_cascade_to_next_candidate_for_calendar_project(): void
    {
        // GIVEN
        Queue::fake();

        $vendorInstitutionUser1 = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor1 = Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser1->id,
            'company_name' => fake()->company(),
        ]);

        $vendorInstitutionUser2 = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor2 = Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser2->id,
            'company_name' => fake()->company(),
        ]);

        $project = $this->createProjectWithAssignment(isCalendar: true);
        $assignment = $project->subProjects->first()->assignments->first();

        $candidate1 = Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor1->id,
            'status' => CandidateStatus::SubmittedToVendor,
            'position' => 1,
            'notified_at' => now(),
        ]);

        $candidate2 = Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor2->id,
            'status' => CandidateStatus::New,
            'position' => 2,
        ]);

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForDecline($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorInstitutionUser1->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/workflow/tasks/{$taskId}/decline");

        // THEN
        $response->assertOk();

        $candidate1->refresh();
        $this->assertEquals(CandidateStatus::Declined, $candidate1->status);

        Queue::assertPushed(ProcessCandidatesNotificationCycle::class);
    }

    public function test_decline_does_not_cascade_for_non_calendar_project(): void
    {
        // GIVEN
        Queue::fake();

        $vendorInstitutionUser1 = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor1 = Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser1->id,
            'company_name' => fake()->company(),
        ]);

        $vendorInstitutionUser2 = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor2 = Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser2->id,
            'company_name' => fake()->company(),
        ]);

        $project = $this->createProjectWithAssignment(isCalendar: false);
        $assignment = $project->subProjects->first()->assignments->first();

        $candidate1 = Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor1->id,
            'status' => CandidateStatus::SubmittedToVendor,
            'position' => 1,
            'notified_at' => now(),
        ]);

        $candidate2 = Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor2->id,
            'status' => CandidateStatus::New,
            'position' => 2,
        ]);

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForDecline($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorInstitutionUser1->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/workflow/tasks/{$taskId}/decline");

        // THEN
        $response->assertOk();

        $candidate1->refresh();
        $this->assertEquals(CandidateStatus::Declined, $candidate1->status);

        // Second candidate should NOT be proposed — no cascade for non-calendar
        $candidate2->refresh();
        $this->assertEquals(CandidateStatus::New, $candidate2->status);
        $this->assertNull($candidate2->notified_at);
    }

    private function createProjectWithAssignment(bool $isCalendar = false): Project
    {
        $project = Project::factory()->create([
            'institution_id' => $this->institution->id,
            'is_calendar_project' => $isCalendar,
            'event_start_at' => $isCalendar ? now()->addDay()->setHour(10) : null,
            'event_end_at' => $isCalendar ? now()->addDay()->setHour(11) : null,
        ]);

        $subProject = SubProject::withoutEvents(fn () => SubProject::factory()->create([
            'project_id' => $project->id,
            'ext_id' => 'TEST-SP-' . fake()->unique()->numberBetween(1, 99999),
            'source_language_classifier_value_id' => $this->sourceLanguage->id,
            'destination_language_classifier_value_id' => $this->destinationLanguage->id,
        ]));

        Assignment::withoutEvents(fn () => Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
            'ext_id' => 'TEST-A-' . fake()->unique()->numberBetween(1, 99999),
        ]));

        return $project->load('subProjects.assignments');
    }

    private function fakeCamundaForDecline(string $taskId, string $executionId, string $assignmentId): void
    {
        // Swap with a fresh Factory to clear the setUp's catch-all pattern stubs
        Http::swap(new HttpFactory());

        Http::fake(function ($request) use ($taskId, $executionId, $assignmentId) {
            if (str_contains($request->url(), '/token') || str_contains($request->url(), '/realms/')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }

            if (str_contains($request->url(), "/task/{$taskId}") && !str_contains($request->url(), 'variable')) {
                return Http::response([
                    'id' => $taskId,
                    'executionId' => $executionId,
                    'assignee' => null,
                    'processInstanceId' => fake()->uuid(),
                    'processDefinitionId' => fake()->uuid(),
                ]);
            }

            if (str_contains($request->url(), '/variable-instance')) {
                return Http::response([
                    [
                        'name' => 'task_type',
                        'value' => 'DEFAULT',
                        'executionId' => $executionId,
                    ],
                    [
                        'name' => 'assignment_id',
                        'value' => $assignmentId,
                        'executionId' => $executionId,
                    ],
                ]);
            }

            return Http::response([], 200);
        });
    }
}
