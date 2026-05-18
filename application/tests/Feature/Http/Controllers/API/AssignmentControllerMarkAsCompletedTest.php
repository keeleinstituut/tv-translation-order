<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\JobKey;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\JobDefinition;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\AuthHelpers;
use Tests\TestCase;

class AssignmentControllerMarkAsCompletedTest extends TestCase
{
    private function makeAssignment(Institution $ownerInstitution): Assignment
    {
        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        return Assignment::factory()->create(['sub_project_id' => $subProject->id]);
    }

    public function test_partner_manager_with_offer_accepted_can_mark_as_completed(): void
    {
        // GIVEN — partner institution manager has ManageProject privilege and OfferAccepted offer
        $ownerInstitution = Institution::factory()->create();
        $partnerManager = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        $projectTypeConfig = ProjectTypeConfig::factory()->create();
        $jobDefinition = JobDefinition::create([
            'project_type_config_id' => $projectTypeConfig->id,
            'job_key' => JobKey::JOB_TRANSLATION,
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 1,
        ]);

        $workflowInstanceRef = fake()->uuid();
        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'workflow_instance_ref' => $workflowInstanceRef,
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'job_definition_id' => $jobDefinition->id,
        ]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Fulfilled,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerManager->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForMarkAsCompleted($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerManager->id,
            'selectedInstitution' => ['id' => $partnerManager->institution['id']],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/assignments/{$assignment->id}/mark-as-completed");

        // THEN — policy passes and assignment is marked as completed
        $response->assertOk();
    }

    private function fakeCamundaForMarkAsCompleted(string $taskId, string $executionId, string $assignmentId): void
    {
        Http::swap(new HttpFactory());

        Http::fake(function ($request) use ($taskId, $executionId, $assignmentId) {
            if (str_contains($request->url(), '/token') || str_contains($request->url(), '/realms/')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }

            if (str_contains($request->url(), '/task/count')) {
                return Http::response(['count' => 1], 200);
            }

            if (str_contains($request->url(), "/task/{$taskId}/complete")) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/task')) {
                return Http::response([[
                    'id' => $taskId,
                    'executionId' => $executionId,
                ]], 200);
            }

            if (str_contains($request->url(), '/variable-instance')) {
                return Http::response([
                    ['name' => 'assignment_id', 'value' => $assignmentId, 'executionId' => $executionId],
                    ['name' => 'task_type', 'value' => 'DEFAULT', 'executionId' => $executionId],
                ], 200);
            }

            return Http::response([], 200);
        });
    }

    public function test_partner_manager_with_declined_offer_cannot_mark_as_completed(): void
    {
        // GIVEN — partner institution manager has ManageProject privilege but only a declined offer
        $ownerInstitution = Institution::factory()->create();
        $partnerManager = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($ownerInstitution);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerManager->institution['id'],
            'status' => OutsourceOfferStatus::RequestDeclined,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerManager->id,
            'selectedInstitution' => ['id' => $partnerManager->institution['id']],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/assignments/{$assignment->id}/mark-as-completed");

        // THEN — assignment is hidden from partner with declined offer (scope requires OfferAccepted)
        $response->assertNotFound();
    }
}
