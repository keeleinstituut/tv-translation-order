<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Volume;
use Tests\AuthHelpers;
use Tests\TestCase;

class ExternalTranslationRequestAccessControlTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Project read — ProjectPolicy::view + ProjectScope
    // -------------------------------------------------------------------------

    public function test_partner_with_notified_recipient_can_read_project(): void
    {
        [$project, , , , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/projects/{$project->id}")
            ->assertOk();
    }

    public function test_partner_with_accepted_recipient_can_read_project(): void
    {
        [$project, , , , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Accepted);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/projects/{$project->id}")
            ->assertOk();
    }

    public function test_partner_with_declined_recipient_cannot_read_project(): void
    {
        [$project, , , , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Declined);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }

    public function test_partner_with_expired_recipient_cannot_read_project(): void
    {
        [$project, , , , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Expired);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }

    public function test_unrelated_institution_cannot_read_shared_project(): void
    {
        [$project] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);
        $unrelatedInstitution = Institution::factory()->create();

        $this->prepareAuthorizedRequest($this->partnerToken($unrelatedInstitution))
            ->getJson("/api/projects/{$project->id}")
            ->assertNotFound();
    }

    public function test_owner_retains_full_project_access(): void
    {
        [$project, , , , , $ownerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $this->prepareAuthorizedRequest($this->ownerToken($ownerInstitution))
            ->getJson("/api/projects/{$project->id}")
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Sub-project read — SubProjectPolicy::view + SubProjectScope
    // -------------------------------------------------------------------------

    public function test_partner_with_notified_recipient_can_read_sub_project(): void
    {
        [, $subProject, , , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/subprojects/{$subProject->id}")
            ->assertOk();
    }

    public function test_partner_with_declined_recipient_cannot_read_sub_project(): void
    {
        [, $subProject, , , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Declined);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/subprojects/{$subProject->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Assignment read — AssignmentPolicy::view + AssignmentScope
    // -------------------------------------------------------------------------

    public function test_partner_with_notified_recipient_can_read_assignment(): void
    {
        [, , $assignment, , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertOk();
    }

    public function test_partner_with_declined_recipient_cannot_read_assignment(): void
    {
        [, , $assignment, , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Declined);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Volume writes — VolumePolicy::update/delete partner deny
    // -------------------------------------------------------------------------

    public function test_partner_cannot_update_volume_even_with_manage_project(): void
    {
        [, , $assignment, , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $volume = Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 1,
            'unit_fee' => 10,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->putJson("/api/volumes/{$volume->id}", [
                'unit_type' => VolumeUnits::Pages->value,
                'unit_quantity' => 2,
                'unit_fee' => 10,
            ])
            ->assertForbidden();
    }

    public function test_partner_cannot_delete_volume_even_with_manage_project(): void
    {
        [, , $assignment, , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $volume = Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 1,
            'unit_fee' => 10,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->deleteJson("/api/volumes/{$volume->id}")
            ->assertForbidden();
    }

    public function test_owner_can_update_volume(): void
    {
        [, , $assignment, , , $ownerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $volume = Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 1,
            'unit_fee' => 10,
        ]);

        $this->prepareAuthorizedRequest($this->ownerToken($ownerInstitution))
            ->putJson("/api/volumes/{$volume->id}", [
                'unit_type' => VolumeUnits::Pages->value,
                'unit_quantity' => 2,
                'unit_fee' => 10,
            ])
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Assignment completion — AssignmentPolicy::markAsCompleted partner branch (D9)
    // -------------------------------------------------------------------------

    public function test_partner_can_mark_assignment_as_completed_when_selected(): void
    {
        // SELECTED recipient: assignment.external_institution_id is set → partner passes policy
        [, , $assignment, , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Selected);
        $assignment->update(['external_institution_id' => $partnerInstitution->id]);

        // Camunda mock returns empty task list → 404 "no task to complete" (NOT 403)
        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->postJson("/api/assignments/{$assignment->id}/mark-as-completed")
            ->assertNotForbidden();
    }

    public function test_partner_cannot_mark_assignment_as_completed_without_external_institution_id(): void
    {
        // NOTIFIED recipient without external_institution_id → policy denies
        [, , $assignment, , , , $partnerInstitution] = $this->createSharedScenario(ExternalRequestRecipientStatus::Notified);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->postJson("/api/assignments/{$assignment->id}/mark-as-completed")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // File downloads — ExternalTranslationRequestPolicy::downloadMedia
    // -------------------------------------------------------------------------

    public function test_partner_can_download_request_files_when_include_source_files_true(): void
    {
        [, , , $translationRequest, , , $partnerInstitution] = $this->createSharedScenario(
            ExternalRequestRecipientStatus::Notified,
            includeSourceFiles: true,
        );

        $media = $translationRequest->addMediaFromString('content')
            ->usingFileName('file.txt')
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);

        $query = http_build_query([
            'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
            'reference_object_id' => $translationRequest->id,
            'reference_object_type' => 'external_translation_request',
            'id' => $media->id,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/media/download?{$query}")
            ->assertOk();
    }

    public function test_partner_cannot_download_request_files_when_project_is_accepted(): void
    {
        [$project, , , $translationRequest, , , $partnerInstitution] = $this->createSharedScenario(
            ExternalRequestRecipientStatus::Notified,
            includeSourceFiles: true,
        );

        $project->update(['status' => ProjectStatus::Accepted]);

        $media = $translationRequest->addMediaFromString('content')
            ->usingFileName('file.txt')
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);

        $query = http_build_query([
            'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
            'reference_object_id' => $translationRequest->id,
            'reference_object_type' => 'external_translation_request',
            'id' => $media->id,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution))
            ->getJson("/api/media/download?{$query}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Builds a minimal shared-project scenario:
     * owner institution → project → sub-project → assignment → request → recipient
     *
     * @return array{Project, SubProject, Assignment, ExternalTranslationRequest, ExternalTranslationRequestRecipient, Institution, Institution}
     */
    private function createSharedScenario(
        ExternalRequestRecipientStatus $recipientStatus,
        bool $includeSourceFiles = true,
    ): array
    {
        $ownerInstitution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $ownerUser = InstitutionUser::factory()
            ->setInstitution(['id' => $ownerInstitution->id, 'name' => $ownerInstitution->name])
            ->create();

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

        $translationRequest = ExternalTranslationRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'created_by_institution_user_id' => $ownerUser->id,
            'status' => ExternalRequestStatus::Active,
            'include_source_files' => $includeSourceFiles,
        ]);

        ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $translationRequest->id,
            'institution_id' => $partnerInstitution->id,
            'position' => 1,
            'status' => $recipientStatus,
            'notified_at' => now(),
            'expires_at' => null,
        ]);

        return [$project, $subProject, $assignment, $translationRequest, null, $ownerInstitution, $partnerInstitution];
    }

    private function partnerToken(Institution $institution): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [
                PrivilegeKey::ViewExternalTranslationRequest->value,
                PrivilegeKey::ManageProject->value,
            ],
        ]);
    }

    private function ownerToken(Institution $institution): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [
                PrivilegeKey::ManageProject->value,
                PrivilegeKey::ViewInstitutionProjectDetail->value,
                PrivilegeKey::ManageExternalTranslationRequest->value,
            ],
        ]);
    }
}
