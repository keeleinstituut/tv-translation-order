<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\Project;
use App\Models\SubProject;
use Tests\AuthHelpers;
use Tests\TestCase;

class ExternalTranslationRequestRecipientControllerTest extends TestCase
{
    // --- accept ---

    public function test_partner_with_respond_privilege_can_accept_their_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $partnerRecipient = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/external-translation-request-recipients/{$partnerRecipient->id}/accept");

        // THEN
        $response->assertOk();
    }

    public function test_partner_without_respond_privilege_cannot_accept(): void
    {
        // GIVEN — partner has ViewETR but not RespondETR
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $partnerRecipient = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/external-translation-request-recipients/{$partnerRecipient->id}/accept");

        // THEN
        $response->assertForbidden();
    }

    public function test_owner_institution_user_cannot_accept_partner_recipient(): void
    {
        // GIVEN — owner tries to accept a recipient that belongs to a different (partner) institution
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $partnerRecipient = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/external-translation-request-recipients/{$partnerRecipient->id}/accept");

        // THEN — accept policy requires recipient.institution_id === user.institutionId
        $response->assertForbidden();
    }

    // --- decline ---

    public function test_partner_with_respond_privilege_can_decline_their_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $partnerRecipient = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/external-translation-request-recipients/{$partnerRecipient->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertOk();
    }

    public function test_partner_without_respond_privilege_cannot_decline(): void
    {
        // GIVEN — partner has ViewETR but not RespondETR
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $partnerRecipient = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/external-translation-request-recipients/{$partnerRecipient->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertForbidden();
    }

    public function test_owner_institution_user_cannot_decline_partner_recipient(): void
    {
        // GIVEN — owner tries to decline a recipient that belongs to a different (partner) institution
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $partnerRecipient = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/external-translation-request-recipients/{$partnerRecipient->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN — decline policy requires recipient.institution_id === user.institutionId
        $response->assertForbidden();
    }

    // --- helpers ---

    private function createOwnerUser(PrivilegeKey ...$extra): InstitutionUser
    {
        return InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageExternalTranslationRequest, ...$extra);
    }

    private function createPartnerUser(PrivilegeKey ...$privileges): InstitutionUser
    {
        return InstitutionUser::factory()->createWithPrivileges(...$privileges);
    }

    private function createAssignmentForOwner(InstitutionUser $ownerUser): Assignment
    {
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        return Assignment::factory()->create(['sub_project_id' => $subProject->id]);
    }

    private function createTranslationRequest(Assignment $assignment, array $overrides = []): ExternalTranslationRequest
    {
        return ExternalTranslationRequest::factory()->create(array_merge([
            'assignment_id' => $assignment->id,
            'status' => ExternalRequestStatus::Active,
        ], $overrides));
    }

    private function createNotifiedRecipient(
        ExternalTranslationRequest $request,
        InstitutionUser $partnerUser,
        array $overrides = []
    ): ExternalTranslationRequestRecipient {
        return ExternalTranslationRequestRecipient::factory()
            ->notified()
            ->create(array_merge([
                'external_translation_request_id' => $request->id,
                'institution_id' => $partnerUser->institution['id'],
            ], $overrides));
    }
}
