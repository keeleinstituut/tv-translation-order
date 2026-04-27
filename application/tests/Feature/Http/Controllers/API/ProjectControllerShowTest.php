<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
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
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);
        $translationRequest = ExternalTranslationRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => ExternalRequestStatus::Active,
        ]);
        ExternalTranslationRequestRecipient::factory()->notified()->create([
            'external_translation_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/projects/{$project->id}");

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $project->id]);
    }

    public function test_partner_cannot_view_project_when_not_a_recipient(): void
    {
        // GIVEN — partner has ViewETR but no recipient record linking them to the project
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
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
            ->createWithPrivileges(PrivilegeKey::ManageExternalTranslationRequest);
    }

    private function createPartnerUser(PrivilegeKey ...$privileges): InstitutionUser
    {
        return InstitutionUser::factory()->createWithPrivileges(...$privileges);
    }
}
