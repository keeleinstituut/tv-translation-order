<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AuthHelpers;
use Tests\TestCase;

class ExternalTranslationRequestControllerTest extends TestCase
{
    // --- index ---

    public function test_partner_can_list_requests_where_they_are_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/external-translation-requests');

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $translationRequest->id]);
    }

    public function test_partner_does_not_see_requests_unrelated_to_their_institution(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        // No recipient for partnerUser's institution

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/external-translation-requests');

        // THEN
        $response->assertOk()
            ->assertJsonMissing(['id' => $translationRequest->id]);
    }

    // --- show ---

    public function test_partner_with_view_privilege_can_view_request_when_they_are_a_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/external-translation-requests/{$translationRequest->id}");

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $translationRequest->id]);
    }

    public function test_partner_cannot_view_request_when_not_a_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        // No recipient for partnerUser's institution — scope excludes the record

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/external-translation-requests/{$translationRequest->id}");

        // THEN
        $response->assertNotFound();
    }

    public function test_partner_without_view_privilege_cannot_view_even_as_recipient(): void
    {
        // GIVEN — partner has only RespondETR, not ViewETR
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/external-translation-requests/{$translationRequest->id}");

        // THEN
        $response->assertForbidden();
    }

    // --- downloadMedia ---

    public function test_partner_with_notified_status_can_download_media(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'external_translation_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertOk();
    }

    public function test_partner_with_accepted_status_can_download_media(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => ExternalRequestRecipientStatus::Accepted,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'external_translation_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertOk();
    }

    public function test_partner_with_selected_status_can_download_media(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => ExternalRequestRecipientStatus::Selected,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'external_translation_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertOk();
    }

    public function test_partner_cannot_download_when_include_source_files_is_false(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, ['include_source_files' => false]);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'external_translation_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertForbidden();
    }

    public function test_partner_cannot_download_when_project_is_accepted(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $project = Project::factory()->create([
            'institution_id' => $ownerUser->institution['id'],
            'status' => \App\Enums\ProjectStatus::Accepted,
        ]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'external_translation_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertForbidden();
    }

    public function test_partner_with_pending_status_cannot_download(): void
    {
        // GIVEN — recipient is Pending, not yet Notified
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewExternalTranslationRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'external_translation_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
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
            'include_source_files' => true,
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
