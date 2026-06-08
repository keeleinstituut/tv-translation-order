<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\OutsourceRequestStatus;
use App\Enums\OutsourceOfferStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\OutsourceRequest;
use App\Models\OutsourceOffer;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Tests\AuthHelpers;
use Tests\TestCase;

class AssignmentControllerShowTest extends TestCase
{
    private Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institution = Institution::factory()->create();
    }

    public function test_assigned_vendor_can_view_assignment(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'sub_project_id',
                    'ext_id',
                    'status',
                    'deadline_at',
                    'comments',
                    'assignee_comments',
                    'created_at',
                    'updated_at',
                    'job_definition',
                    'assignee',
                    'candidates',
                    'volumes',
                    'cat_jobs',
                    'subProject',
                    'outsource_requests',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $assignment->id,
                    'outsource_requests' => [],
                ],
            ]);
    }

    public function test_candidate_vendor_can_view_assignment(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $assignment->id,
                ],
            ]);
    }

    public function test_unrelated_vendor_gets_forbidden(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertForbidden();
    }

    public function test_vendor_from_different_institution_gets_not_found(): void
    {
        // GIVEN
        $otherInstitution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $otherInstitution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $otherInstitution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertNotFound();
    }

    public function test_partner_with_view_etr_privilege_can_view_assignment_when_active_recipient(): void
    {
        // GIVEN
        $ownerInstitution = Institution::factory()->create();
        $partnerUser = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ViewOutsourceRequest);

        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerUser->id,
            'selectedInstitution' => ['id' => $partnerUser->institution['id']],
            'privileges' => [PrivilegeKey::ViewOutsourceRequest->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertOk()
            ->assertJson(['data' => ['id' => $assignment->id]])
            ->assertJsonPath('data.outsource_requests.0.id', $outsourceRequest->id);
    }

    public function test_partner_cannot_view_assignment_when_not_a_recipient(): void
    {
        // GIVEN — partner has ViewETR but no recipient record for this assignment
        $ownerInstitution = Institution::factory()->create();
        $partnerUser = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ViewOutsourceRequest);

        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerUser->id,
            'selectedInstitution' => ['id' => $partnerUser->institution['id']],
            'privileges' => [PrivilegeKey::ViewOutsourceRequest->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN — AssignmentScope excludes the assignment; findOrFail returns 404
        $response->assertNotFound();
    }

    public function test_partner_vendor_candidate_can_view_assignment_without_view_etr_privilege(): void
    {
        // GIVEN — partner institution vendor is a candidate but has no ViewOutsourceRequest privilege
        $ownerInstitution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();
        $partnerInstitutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $partnerInstitution->id])
            ->create();
        $partnerVendor = Vendor::factory()->create(['institution_user_id' => $partnerInstitutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerInstitution->id,
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);
        Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $partnerVendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerInstitutionUser->id,
            'selectedInstitution' => ['id' => $partnerInstitution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN — vendor is a candidate so can view even without ViewOutsourceRequest privilege
        $response->assertOk()->assertJsonPath('data.id', $assignment->id);
    }

    public function test_partner_vendor_assignee_can_view_assignment_without_view_etr_privilege(): void
    {
        // GIVEN — partner institution vendor is the assignee but has no ViewOutsourceRequest privilege
        $ownerInstitution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();
        $partnerInstitutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $partnerInstitution->id])
            ->create();
        $partnerVendor = Vendor::factory()->create(['institution_user_id' => $partnerInstitutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $partnerVendor->id,
        ]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerInstitution->id,
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerInstitutionUser->id,
            'selectedInstitution' => ['id' => $partnerInstitution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN — vendor is the assignee so can view even without ViewOutsourceRequest privilege
        $response->assertOk()->assertJsonPath('data.id', $assignment->id);
    }

    public function test_outsource_requests_returns_full_history_ordered_newest_first(): void
    {
        // GIVEN — owner creates an assignment, creates a request, cancels it, creates a new one
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $cancelledRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Cancelled,
            'created_at' => now()->subMinutes(5),
        ]);
        $activeRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
            'created_at' => now(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [PrivilegeKey::ViewInstitutionProjectDetail->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN — both requests returned, newest first
        $response->assertOk()
            ->assertJsonCount(2, 'data.outsource_requests')
            ->assertJsonPath('data.outsource_requests.0.id', $activeRequest->id)
            ->assertJsonPath('data.outsource_requests.0.status', OutsourceRequestStatus::Active->value)
            ->assertJsonPath('data.outsource_requests.1.id', $cancelledRequest->id)
            ->assertJsonPath('data.outsource_requests.1.status', OutsourceRequestStatus::Cancelled->value);
    }

    public function test_external_agency_only_sees_outsource_requests_with_their_offer(): void
    {
        // GIVEN — two outsource requests on the same assignment; partner has an offer only on one
        $ownerInstitution = Institution::factory()->create();
        $partnerUser = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ViewOutsourceRequest);

        $project = Project::factory()->create(['institution_id' => $ownerInstitution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $requestWithOffer = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
            'created_at' => now(),
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $requestWithOffer->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Cancelled,
            'created_at' => now()->subMinutes(5),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $partnerUser->id,
            'selectedInstitution' => ['id' => $partnerUser->institution['id']],
            'privileges' => [PrivilegeKey::ViewOutsourceRequest->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN — only the request with an offer for this partner is visible
        $response->assertOk()
            ->assertJsonCount(1, 'data.outsource_requests')
            ->assertJsonPath('data.outsource_requests.0.id', $requestWithOffer->id);
    }

    public function test_response_includes_sub_project_with_languages_and_project(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'subProject' => [
                        'id',
                        'project_id',
                        'source_language_classifier_value',
                        'destination_language_classifier_value',
                        'project' => [
                            'id',
                            'type_classifier_value',
                            'translation_domain_classifier_value',
                            'tags',
                        ],
                    ],
                ],
            ]);
    }
}
