<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\SubProject;
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

    public function test_partner_manager_with_offer_accepted_can_pass_policy_for_mark_as_completed(): void
    {
        // GIVEN — partner institution manager has ManageProject privilege and OfferAccepted offer
        $ownerInstitution = Institution::factory()->create();
        $partnerManager = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($ownerInstitution);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Fulfilled,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerManager->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerManager->id,
            'selectedInstitution' => ['id' => $partnerManager->institution['id']],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/assignments/{$assignment->id}/mark-as-completed");

        // THEN — policy passes; workflow has no task in test env so 404 is expected (not 403)
        $response->assertNotFound();
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

        // THEN — policy rejects: only OfferAccepted partners may complete the assignment
        $response->assertForbidden();
    }
}
