<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Volume;
use Tests\AuthHelpers;
use Tests\TestCase;

class VolumeControllerCrossInstitutionTest extends TestCase
{
    public function test_partner_cannot_update_volume_even_with_manage_project_privilege(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        [$assignment, $volume] = $this->createAssignmentWithVolume($ownerUser);
        $this->createActiveRecipient($assignment, $partnerUser);

        // WHEN — partner with ManageProject tries to update a volume on a shared assignment
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->putJson("/api/volumes/{$volume->id}", [
                'unit_type' => VolumeUnits::Words->value,
                'unit_quantity' => '10.000',
                'unit_fee' => '5.000',
            ]);

        // THEN — VolumePolicy::update denies partner even with ManageProject
        $response->assertForbidden();
    }

    public function test_partner_cannot_delete_volume_even_with_manage_project_privilege(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        [$assignment, $volume] = $this->createAssignmentWithVolume($ownerUser);
        $this->createActiveRecipient($assignment, $partnerUser);

        // WHEN — partner with ManageProject tries to delete a volume on a shared assignment
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->deleteJson("/api/volumes/{$volume->id}");

        // THEN — VolumePolicy::delete denies partner even with ManageProject
        $response->assertForbidden();
    }

    // --- helpers ---

    private function createOwnerUser(): InstitutionUser
    {
        return InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageProject);
    }

    /**
     * @return array{Assignment, Volume}
     */
    private function createAssignmentWithVolume(InstitutionUser $ownerUser): array
    {
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);
        $subProject = SubProject::factory()->create(['project_id' => $project->id]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $volume = Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Words,
            'unit_quantity' => '10.000',
            'unit_fee' => '5.000',
        ]);

        return [$assignment, $volume];
    }

    private function createActiveRecipient(Assignment $assignment, InstitutionUser $partnerUser): ExternalTranslationRequestRecipient
    {
        $translationRequest = ExternalTranslationRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => ExternalRequestStatus::Active,
        ]);

        return ExternalTranslationRequestRecipient::factory()->notified()->create([
            'external_translation_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
        ]);
    }
}
