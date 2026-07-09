<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\OutsourceRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\OutsourceRequest;
use App\Models\OutsourceOffer;
use App\Models\Project;
use App\Models\SubProject;
use Tests\AuthHelpers;
use Tests\TestCase;

class ProjectControllerShowTest extends TestCase
{
    public function test_partner_with_view_etr_privilege_can_view_project_when_active_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);
        $translationRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => \App\Enums\OutsourceOfferStatus::OfferAccepted,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/projects/{$project->id}");

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $project->id]);
    }

    public function test_partner_sees_only_subprojects_with_shared_assignments(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);

        $sharedSubProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $sharedAssignment = Assignment::factory()->create(['sub_project_id' => $sharedSubProject->id]);
        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $sharedAssignment->id,
            'status' => OutsourceRequestStatus::Active,
        ]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => \App\Enums\OutsourceOfferStatus::OfferAccepted,
        ]);

        $unsharedSubProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        Assignment::factory()->create(['sub_project_id' => $unsharedSubProject->id]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/projects/{$project->id}");

        // THEN
        $response->assertOk();
        $subProjectIds = collect($response->json('data.sub_projects'))->pluck('id');
        $this->assertTrue($subProjectIds->contains($sharedSubProject->id));
        $this->assertFalse($subProjectIds->contains($unsharedSubProject->id));
    }

    public function test_partner_cannot_view_project_when_not_a_recipient(): void
    {
        // GIVEN — partner has ViewETR but no recipient record linking them to the project
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/projects/{$project->id}");

        // THEN — ProjectScope excludes the project; findOrFail returns 404
        $response->assertNotFound();
    }

    private function createOwnerUser(): InstitutionUser
    {
        return InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageOutsourceRequest);
    }

    private function createPartnerUser(PrivilegeKey ...$privileges): InstitutionUser
    {
        return InstitutionUser::factory()->createWithPrivileges(...$privileges);
    }
}
