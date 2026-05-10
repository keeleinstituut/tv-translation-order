<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\CandidateStatus;
use App\Enums\ExternalRequestMode;
use App\Enums\InstitutionType;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Enums\PrivilegeKey;
use App\Jobs\ExpireOutsourceOfferJob;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\InstitutionPartner;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\AuthHelpers;
use Tests\TestCase;

class OutsourceRequestControllerTest extends TestCase
{
    // --- store ---

    public function test_owner_can_create_cascade_request(): void
    {
        // GIVEN
        Queue::fake();
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createInstitutionPartner($ownerUser, $partnerA);
        $this->createInstitutionPartner($ownerUser, $partnerB);

        // WHEN
        $response = $this->withHeaders($this->jsonHeadersFor($ownerUser))
            ->post('/api/outsource-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Cascade->value,
                'reaction_time_minutes' => 30,
                'recipients' => [
                    ['institution_id' => $partnerA->institution['id']],
                    ['institution_id' => $partnerB->institution['id']],
                ],
                'special_instructions' => 'Please preserve formatting.',
                'include_price' => false,
                'include_source_files' => true,
                'override_price' => 123.456,
                'request_files' => [
                    UploadedFile::fake()->create('source.docx'),
                ],
            ]);

        // THEN
        $response->assertCreated();
        $outsourceRequest = OutsourceRequest::query()
            ->with('offers')
            ->where('assignment_id', $assignment->id)
            ->firstOrFail();
        $offers = $outsourceRequest->offers()->orderBy('position')->get();

        $this->assertSame(OutsourceRequestStatus::Active, $outsourceRequest->status);
        $this->assertSame(ExternalRequestMode::Cascade, $outsourceRequest->mode);
        $this->assertSame(30, $outsourceRequest->reaction_time_minutes);
        $this->assertNull($outsourceRequest->deadline_at);
        $this->assertSame('Please preserve formatting.', $outsourceRequest->special_instructions);
        $this->assertSame('123.456', $outsourceRequest->price);
        $this->assertFalse($outsourceRequest->include_price);
        $this->assertTrue($outsourceRequest->include_source_files);
        $this->assertCount(1, $outsourceRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION));
        $this->assertCount(2, $offers);
        $this->assertSame(1, $offers[0]->position);
        $this->assertSame($partnerA->institution['id'], $offers[0]->institution_id);
        $this->assertSame(OutsourceOfferStatus::RequestSent, $offers[0]->status);
        $this->assertNotNull($offers[0]->notified_at);
        $this->assertNotNull($offers[0]->expires_at);
        $this->assertSame(2, $offers[1]->position);
        $this->assertSame($partnerB->institution['id'], $offers[1]->institution_id);
        $this->assertSame(OutsourceOfferStatus::RequestPending, $offers[1]->status);
        $this->assertNull($offers[1]->notified_at);
        $this->assertNull($offers[1]->expires_at);
        Queue::assertPushed(ExpireOutsourceOfferJob::class, 1);
        Queue::assertPushed(
            ExpireOutsourceOfferJob::class,
            fn (ExpireOutsourceOfferJob $job) => $job->recipientId === $offers[0]->id
        );
    }

    public function test_owner_can_create_parallel_request(): void
    {
        // GIVEN
        Queue::fake();
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $deadline = now()->addDays(2)->milliseconds(0);
        $this->createInstitutionPartner($ownerUser, $partnerA);
        $this->createInstitutionPartner($ownerUser, $partnerB);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => $deadline->toISOString(),
                'recipients' => [
                    ['institution_id' => $partnerA->institution['id']],
                    ['institution_id' => $partnerB->institution['id']],
                ],
            ]);

        // THEN
        $response->assertCreated();
        $outsourceRequest = OutsourceRequest::query()
            ->with('offers')
            ->where('assignment_id', $assignment->id)
            ->firstOrFail();
        $offers = $outsourceRequest->offers()->orderBy('position')->get();

        $this->assertSame(ExternalRequestMode::Parallel, $outsourceRequest->mode);
        $this->assertNull($outsourceRequest->reaction_time_minutes);
        $this->assertTrue($deadline->equalTo($outsourceRequest->deadline_at));
        $this->assertCount(2, $offers);
        $offers->each(function (OutsourceOffer $offer) use ($deadline): void {
            $this->assertSame(OutsourceOfferStatus::RequestSent, $offer->status);
            $this->assertNotNull($offer->notified_at);
            $this->assertTrue($deadline->equalTo($offer->expires_at));
        });
        Queue::assertPushed(ExpireOutsourceOfferJob::class, 2);
    }

    public function test_partner_cannot_create_request_for_another_institution_assignment(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ManageOutsourceRequest);
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createInstitutionPartner($partnerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertForbidden();
        $this->assertDatabaseMissing('outsource_requests', ['assignment_id' => $assignment->id]);
    }

    public function test_user_without_manage_privilege_cannot_create_request(): void
    {
        // GIVEN
        $ownerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertForbidden();
        $this->assertDatabaseMissing('outsource_requests', ['assignment_id' => $assignment->id]);
    }

    public function test_translation_agency_user_cannot_create_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        Institution::query()
            ->whereKey($ownerUser->institution['id'])
            ->update(['type' => InstitutionType::TranslationAgency]);
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertForbidden();
        $this->assertDatabaseMissing('outsource_requests', ['assignment_id' => $assignment->id]);
    }

    public function test_create_request_requires_mode_specific_deadline_fields(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $cascadeResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', [
                ...$this->validCreatePayload($assignment, [$recipientUser]),
                'reaction_time_minutes' => null,
            ]);
        $parallelResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', [
                ...$this->validCreatePayload($assignment, [$recipientUser]),
                'mode' => ExternalRequestMode::Parallel->value,
                'reaction_time_minutes' => null,
                'deadline_at' => null,
            ]);

        // THEN
        $cascadeResponse->assertUnprocessable()
            ->assertJsonValidationErrors('reaction_time_minutes');
        $parallelResponse->assertUnprocessable()
            ->assertJsonValidationErrors('deadline_at');
    }

    public function test_create_request_rejects_duplicate_recipients(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', [
                ...$this->validCreatePayload($assignment, [$recipientUser]),
                'recipients' => [
                    ['institution_id' => $recipientUser->institution['id']],
                    ['institution_id' => $recipientUser->institution['id']],
                ],
            ]);

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('recipients');
    }

    public function test_create_request_rejects_non_partner_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('recipients.0.institution_id');
    }

    public function test_create_request_rejects_assignment_that_already_has_vendor(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser, [
            'assigned_vendor_id' => Vendor::factory(),
        ]);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('assignment_id');
    }

    public function test_create_request_rejects_assignment_with_active_vendor_candidates(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'status' => CandidateStatus::New,
        ]);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('assignment_id');
    }

    public function test_create_request_rejects_assignment_already_shared_with_external_institution(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $existingRequest = $this->createTranslationRequest($assignment, [
            'status' => OutsourceRequestStatus::Fulfilled,
        ]);
        $this->createNotifiedRecipient($existingRequest, $recipientUser, [
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('assignment_id');
    }

    public function test_create_request_rejects_assignment_with_active_outsource_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $recipientUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $this->createTranslationRequest($assignment);
        $this->createInstitutionPartner($ownerUser, $recipientUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson('/api/outsource-requests', $this->validCreatePayload($assignment, [$recipientUser]));

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('assignment_id');
    }

    // --- index ---

    public function test_partner_can_list_requests_where_they_are_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/outsource-requests');

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $translationRequest->id]);
    }

    public function test_partner_does_not_see_requests_unrelated_to_their_institution(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        // No recipient for partnerUser's institution

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/outsource-requests');

        // THEN
        $response->assertOk()
            ->assertJsonMissing(['id' => $translationRequest->id]);
    }

    public function test_owner_can_filter_index_by_assignment_sub_project_project_and_status(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $matchingAssignment = $this->createAssignmentForOwner($ownerUser);
        $otherAssignment = $this->createAssignmentForOwner($ownerUser);
        $fulfilledAssignment = $this->createAssignmentForOwner($ownerUser);
        $matchingRequest = $this->createTranslationRequest($matchingAssignment);
        $otherRequest = $this->createTranslationRequest($otherAssignment);
        $fulfilledRequest = $this->createTranslationRequest($fulfilledAssignment, [
            'status' => OutsourceRequestStatus::Fulfilled,
        ]);

        // WHEN
        $assignmentResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->getJson('/api/outsource-requests?' . http_build_query([
                'assignment_id' => $matchingAssignment->id,
            ]));
        $subProjectResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->getJson('/api/outsource-requests?' . http_build_query([
                'sub_project_id' => $matchingAssignment->sub_project_id,
            ]));
        $projectResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->getJson('/api/outsource-requests?' . http_build_query([
                'project_id' => $matchingAssignment->subProject->project_id,
            ]));
        $statusResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->getJson('/api/outsource-requests?' . http_build_query([
                'status' => [OutsourceRequestStatus::Fulfilled->value],
            ]));

        // THEN
        $assignmentResponse->assertOk()
            ->assertJsonFragment(['id' => $matchingRequest->id])
            ->assertJsonMissing(['id' => $fulfilledRequest->id])
            ->assertJsonMissing(['id' => $otherRequest->id]);
        $subProjectResponse->assertOk()
            ->assertJsonFragment(['id' => $matchingRequest->id])
            ->assertJsonMissing(['id' => $fulfilledRequest->id])
            ->assertJsonMissing(['id' => $otherRequest->id]);
        $projectResponse->assertOk()
            ->assertJsonFragment(['id' => $matchingRequest->id])
            ->assertJsonMissing(['id' => $fulfilledRequest->id])
            ->assertJsonMissing(['id' => $otherRequest->id]);
        $statusResponse->assertOk()
            ->assertJsonFragment(['id' => $fulfilledRequest->id])
            ->assertJsonMissing(['id' => $matchingRequest->id])
            ->assertJsonMissing(['id' => $otherRequest->id]);
    }

    public function test_owner_can_sort_and_paginate_index(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $olderAssignment = $this->createAssignmentForOwner($ownerUser);
        $newerAssignment = $this->createAssignmentForOwner($ownerUser);
        $olderRequest = $this->createTranslationRequest($olderAssignment, [
            'created_at' => now()->subDay(),
        ]);
        $newerRequest = $this->createTranslationRequest($newerAssignment, [
            'created_at' => now(),
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->getJson('/api/outsource-requests?' . http_build_query([
                'sort_order' => 'asc',
                'per_page' => 1,
            ]));

        // THEN
        $response->assertOk()
            ->assertJsonPath('data.0.id', $olderRequest->id)
            ->assertJsonMissing(['id' => $newerRequest->id])
            ->assertJsonPath('meta.per_page', 1);
        $this->assertCount(1, $response->json('data'));
    }

    // --- show ---

    public function test_partner_with_view_privilege_can_view_request_when_they_are_a_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/outsource-requests/{$translationRequest->id}");

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $translationRequest->id]);
    }

    public function test_partner_cannot_view_request_when_not_a_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        // No recipient for partnerUser's institution — scope excludes the record

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/outsource-requests/{$translationRequest->id}");

        // THEN
        $response->assertNotFound();
    }

    public function test_partner_with_only_respond_privilege_can_view_request_when_they_are_a_recipient(): void
    {
        // GIVEN — partner has only RespondETR (no ViewETR / ManageETR)
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/outsource-requests/{$translationRequest->id}");

        // THEN — Respond privilege is sufficient for the partner branch of view()
        $response->assertOk()
            ->assertJsonFragment(['id' => $translationRequest->id]);
    }

    public function test_user_with_no_etr_privilege_cannot_view_or_list(): void
    {
        // GIVEN — user has an unrelated privilege only
        $ownerUser = $this->createOwnerUser();
        $strangerUser = $this->createPartnerUser(PrivilegeKey::ViewVendorDatabase);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        // WHEN
        $listResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($strangerUser))
            ->getJson('/api/outsource-requests');
        $showResponse = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($strangerUser))
            ->getJson("/api/outsource-requests/{$translationRequest->id}");

        // THEN — viewAny rejects them
        $listResponse->assertForbidden();
        $showResponse->assertForbidden();
    }

    // --- recipients[] filtering ---

    public function test_owner_sees_all_recipients_in_show_response(): void
    {
        // GIVEN — request with two partners
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $recipientA = $this->createNotifiedRecipient($translationRequest, $partnerA);
        $recipientB = $this->createNotifiedRecipient($translationRequest, $partnerB);

        // WHEN owner reads the request
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->getJson("/api/outsource-requests/{$translationRequest->id}");

        // THEN owner sees both recipient rows
        $response->assertOk()
            ->assertJsonPath('data.offers.0.id', fn($id) => in_array($id, [$recipientA->id, $recipientB->id]))
            ->assertJsonPath('data.offers.1.id', fn($id) => in_array($id, [$recipientA->id, $recipientB->id]));
        $this->assertCount(2, $response->json('data.offers'));
    }

    public function test_partner_sees_only_their_own_recipient_row_in_show_response(): void
    {
        // GIVEN — request with two partners
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $recipientA = $this->createNotifiedRecipient($translationRequest, $partnerA);
        $recipientB = $this->createNotifiedRecipient($translationRequest, $partnerB);

        // WHEN partner A reads the request
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerA))
            ->getJson("/api/outsource-requests/{$translationRequest->id}");

        // THEN partner A sees only their own recipient row, not the competitor's
        $response->assertOk()
            ->assertJsonFragment(['id' => $recipientA->id])
            ->assertJsonMissing(['id' => $recipientB->id]);
        $this->assertCount(1, $response->json('data.offers'));
    }

    public function test_partner_sees_only_their_own_recipient_row_in_index_response(): void
    {
        // GIVEN — request with two partners
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $recipientA = $this->createNotifiedRecipient($translationRequest, $partnerA);
        $recipientB = $this->createNotifiedRecipient($translationRequest, $partnerB);

        // WHEN partner A lists requests
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerA))
            ->getJson('/api/outsource-requests');

        // THEN the embedded recipients[] for the request contains only partner A's row
        $response->assertOk()
            ->assertJsonFragment(['id' => $recipientA->id])
            ->assertJsonMissing(['id' => $recipientB->id]);
        $this->assertCount(1, $response->json('data.0.offers'));
    }

    // --- downloadMedia ---

    public function test_partner_with_notified_status_can_download_media(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
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
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::RequestAccepted,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
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
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertOk();
    }

    public function test_selected_partner_can_download_when_include_source_files_is_false(): void
    {
        // GIVEN
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, ['include_source_files' => false]);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
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
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, ['include_source_files' => false]);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
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
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
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
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
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
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        $translationRequest->addMedia(UploadedFile::fake()->create('file.docx'))
            ->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
        $media = $translationRequest->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION)->first();

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/media/download?' . http_build_query([
                'reference_object_type' => 'outsource_request',
                'reference_object_id' => $translationRequest->id,
                'collection' => OutsourceRequest::REQUEST_FILES_COLLECTION,
                'id' => $media->id,
            ]));

        // THEN
        $response->assertForbidden();
    }

    // --- update ---

    public function test_owner_can_reorder_pending_cascade_offers(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $notified = OutsourceOffer::factory()->notified()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 1,
        ]);
        $pendingA = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);
        $pendingB = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 3,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->putJson("/api/outsource-requests/{$translationRequest->id}", [
                'recipients' => [
                    ['id' => $pendingA->id, 'position' => 3],
                    ['id' => $pendingB->id, 'position' => 2],
                ],
            ]);

        // THEN
        $response->assertOk();
        $this->assertSame(1, $notified->fresh()->position);
        $this->assertSame(3, $pendingA->fresh()->position);
        $this->assertSame(2, $pendingB->fresh()->position);
    }

    public function test_partner_cannot_reorder_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ManageOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);
        $pending = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->putJson("/api/outsource-requests/{$translationRequest->id}", [
                'recipients' => [
                    ['id' => $pending->id, 'position' => 1],
                ],
            ]);

        // THEN
        $response->assertForbidden();
        $this->assertSame(2, $pending->fresh()->position);
    }

    public function test_parallel_request_cannot_be_reordered(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'mode' => ExternalRequestMode::Parallel,
        ]);
        $pending = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 1,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->putJson("/api/outsource-requests/{$translationRequest->id}", [
                'recipients' => [
                    ['id' => $pending->id, 'position' => 2],
                ],
            ]);

        // THEN
        $response->assertForbidden();
        $this->assertSame(1, $pending->fresh()->position);
    }

    public function test_non_active_request_cannot_be_reordered(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
            'status' => OutsourceRequestStatus::Fulfilled,
        ]);
        $pending = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 1,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->putJson("/api/outsource-requests/{$translationRequest->id}", [
                'recipients' => [
                    ['id' => $pending->id, 'position' => 2],
                ],
            ]);

        // THEN
        $response->assertForbidden();
        $this->assertSame(1, $pending->fresh()->position);
    }

    public function test_reorder_requires_exact_pending_offer_permutation(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $pendingA = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 1,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);
        $pendingB = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->putJson("/api/outsource-requests/{$translationRequest->id}", [
                'recipients' => [
                    ['id' => $pendingA->id, 'position' => 2],
                ],
            ]);

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('recipients');
        $this->assertSame(1, $pendingA->fresh()->position);
        $this->assertSame(2, $pendingB->fresh()->position);
    }

    public function test_reorder_requires_unique_positions(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $pendingA = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 1,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);
        $pendingB = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->putJson("/api/outsource-requests/{$translationRequest->id}", [
                'recipients' => [
                    ['id' => $pendingA->id, 'position' => 1],
                    ['id' => $pendingB->id, 'position' => 1],
                ],
            ]);

        // THEN
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('recipients');
    }

    // --- cancel ---

    public function test_owner_can_cancel_active_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $pending = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);
        $notified = OutsourceOffer::factory()->notified()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestSent,
        ]);
        $accepted = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
        ]);
        $declined = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestDeclined,
        ]);
        $rejected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::OfferDeclined,
        ]);
        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/cancel");

        // THEN
        $response->assertOk();
        $this->assertSame(OutsourceRequestStatus::Cancelled, $translationRequest->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestExpired, $pending->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestExpired, $notified->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $accepted->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestDeclined, $declined->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $rejected->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::OfferAccepted, $selected->fresh()->status);
    }

    public function test_partner_cannot_cancel_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ManageOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/cancel");

        // THEN
        $response->assertForbidden();
        $this->assertSame(OutsourceRequestStatus::Active, $translationRequest->fresh()->status);
    }

    public function test_cancel_non_active_request_returns_conflict(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'status' => OutsourceRequestStatus::Fulfilled,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/cancel");

        // THEN
        $response->assertConflict();
        $this->assertSame(OutsourceRequestStatus::Fulfilled, $translationRequest->fresh()->status);
    }

    // --- select ---

    public function test_owner_can_select_recipient_with_rejection_comments_for_other_in_play_recipients(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'proposed_price' => '123.456',
            'position' => 1,
        ]);
        $loserAccepted = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 2,
        ]);
        $loserNotified = OutsourceOffer::factory()->notified()->create([
            'outsource_request_id' => $translationRequest->id,
            'position' => 3,
        ]);
        $loserPending = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestPending,
            'position' => 4,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [
                    ['recipient_id' => $loserAccepted->id, 'rejection_comment' => 'price too high'],
                    ['recipient_id' => $loserNotified->id, 'rejection_comment' => 'no answer in time window'],
                    ['recipient_id' => $loserPending->id, 'rejection_comment' => 'not needed'],
                ],
            ]);

        // THEN
        $response->assertOk();
        $this->assertSame(OutsourceRequestStatus::Fulfilled, $translationRequest->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::OfferAccepted, $selected->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $loserAccepted->fresh()->status);
        $this->assertSame('price too high', $loserAccepted->fresh()->rejection_comment);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $loserNotified->fresh()->status);
        $this->assertSame('no answer in time window', $loserNotified->fresh()->rejection_comment);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $loserPending->fresh()->status);
        $this->assertSame('not needed', $loserPending->fresh()->rejection_comment);
    }

    public function test_owner_can_select_when_no_other_in_play_recipients(): void
    {
        // GIVEN — only the selected recipient is in-play; everyone else is terminal
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'proposed_price' => '99.000',
            'position' => 1,
        ]);
        $declined = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestDeclined,
            'decline_comment' => 'self-declined',
            'position' => 2,
        ]);
        $expired = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestExpired,
            'position' => 3,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [],
            ]);

        // THEN
        $response->assertOk();
        $this->assertSame(OutsourceOfferStatus::OfferAccepted, $selected->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestDeclined, $declined->fresh()->status);
        $this->assertSame('self-declined', $declined->fresh()->decline_comment);
        $this->assertNull($declined->fresh()->rejection_comment);
        $this->assertSame(OutsourceOfferStatus::RequestExpired, $expired->fresh()->status);
        $this->assertNull($expired->fresh()->rejection_comment);
    }

    public function test_select_fails_when_missing_rejection_comment_for_in_play_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 1,
        ]);
        $loser = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 2,
        ]);

        // WHEN — omit $loser from rejection_comments
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [],
            ]);

        // THEN
        $response->assertUnprocessable();
        $this->assertSame(OutsourceRequestStatus::Active, $translationRequest->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $loser->fresh()->status);
    }

    public function test_select_fails_when_rejection_comments_include_terminal_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 1,
        ]);
        $declined = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestDeclined,
            'decline_comment' => 'self-declined',
            'position' => 2,
        ]);

        // WHEN — DECLINED recipient must not appear in rejection_comments
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [
                    ['recipient_id' => $declined->id, 'rejection_comment' => 'no'],
                ],
            ]);

        // THEN
        $response->assertUnprocessable();
        $this->assertSame(OutsourceOfferStatus::RequestDeclined, $declined->fresh()->status);
        $this->assertNull($declined->fresh()->rejection_comment);
        $this->assertSame('self-declined', $declined->fresh()->decline_comment);
    }

    public function test_select_fails_when_rejection_comments_include_selected_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 1,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [
                    ['recipient_id' => $selected->id, 'rejection_comment' => 'self'],
                ],
            ]);

        // THEN
        $response->assertUnprocessable();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $selected->fresh()->status);
    }

    public function test_select_fails_when_rejection_comments_include_foreign_recipient(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 1,
        ]);
        $foreignAssignment = $this->createAssignmentForOwner($ownerUser);
        $foreignRequest = $this->createTranslationRequest($foreignAssignment);
        $foreignRecipient = OutsourceOffer::factory()->create([
            'outsource_request_id' => $foreignRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [
                    ['recipient_id' => $foreignRecipient->id, 'rejection_comment' => 'foreign'],
                ],
            ]);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_select_fails_when_rejection_comments_have_duplicate_recipient_id(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 1,
        ]);
        $loser = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 2,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [
                    ['recipient_id' => $loser->id, 'rejection_comment' => 'first'],
                    ['recipient_id' => $loser->id, 'rejection_comment' => 'duplicate'],
                ],
            ]);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_select_fails_when_rejection_comment_is_empty(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);

        $selected = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 1,
        ]);
        $loser = OutsourceOffer::factory()->create([
            'outsource_request_id' => $translationRequest->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'position' => 2,
        ]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/select", [
                'recipient_id' => $selected->id,
                'rejection_comments' => [
                    ['recipient_id' => $loser->id, 'rejection_comment' => ''],
                ],
            ]);

        // THEN
        $response->assertUnprocessable();
    }

    // --- accept ---

    public function test_partner_with_respond_privilege_can_accept_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/accept");

        // THEN
        $response->assertOk();
    }

    public function test_partner_without_respond_privilege_cannot_accept(): void
    {
        // GIVEN — partner has ViewETR but not RespondETR
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/accept");

        // THEN
        $response->assertForbidden();
    }

    public function test_owner_institution_user_cannot_accept_their_own_request(): void
    {
        // GIVEN — owner has no recipient row, even with the Respond privilege
        $ownerUser = $this->createOwnerUser(PrivilegeKey::RespondOutsourceRequest);
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/accept");

        // THEN — accept policy requires the caller's institution to be among recipients
        $response->assertForbidden();
    }

    // --- decline ---

    public function test_partner_with_respond_privilege_can_decline_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertOk();
    }

    public function test_partner_without_respond_privilege_cannot_decline(): void
    {
        // GIVEN — partner has ViewETR but not RespondETR
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertForbidden();
    }

    public function test_owner_institution_user_cannot_decline_their_own_request(): void
    {
        // GIVEN — owner has no recipient row, even with the Respond privilege
        $ownerUser = $this->createOwnerUser(PrivilegeKey::RespondOutsourceRequest);
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-requests/{$translationRequest->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN — decline policy requires the caller's institution to be among recipients
        $response->assertForbidden();
    }

    // --- helpers ---

    private function createOwnerUser(PrivilegeKey ...$extra): InstitutionUser
    {
        return InstitutionUser::factory()
            ->createWithPrivileges(PrivilegeKey::ManageOutsourceRequest, ...$extra);
    }

    private function createPartnerUser(PrivilegeKey ...$privileges): InstitutionUser
    {
        return InstitutionUser::factory()->createWithPrivileges(...$privileges);
    }

    private function createAssignmentForOwner(InstitutionUser $ownerUser, array $overrides = []): Assignment
    {
        $project = Project::factory()->create(['institution_id' => $ownerUser->institution['id']]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        return Assignment::factory()->create(array_merge([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ], $overrides));
    }

    private function createTranslationRequest(Assignment $assignment, array $overrides = []): OutsourceRequest
    {
        return OutsourceRequest::factory()->create(array_merge([
            'assignment_id' => $assignment->id,
            'status' => OutsourceRequestStatus::Active,
            'include_source_files' => true,
        ], $overrides));
    }

    private function createNotifiedRecipient(
        OutsourceRequest $request,
        InstitutionUser $partnerUser,
        array $overrides = []
    ): OutsourceOffer {
        return OutsourceOffer::factory()
            ->notified()
            ->create(array_merge([
                'outsource_request_id' => $request->id,
                'institution_id' => $partnerUser->institution['id'],
            ], $overrides));
    }

    private function createInstitutionPartner(InstitutionUser $ownerUser, InstitutionUser $partnerUser): InstitutionPartner
    {
        return InstitutionPartner::factory()->create([
            'institution_id' => $ownerUser->institution['id'],
            'partner_institution_id' => $partnerUser->institution['id'],
        ]);
    }

    /**
     * @param array<int, InstitutionUser> $recipients
     * @return array<string, mixed>
     */
    private function validCreatePayload(Assignment $assignment, array $recipients): array
    {
        return [
            'assignment_id' => $assignment->id,
            'mode' => ExternalRequestMode::Cascade->value,
            'reaction_time_minutes' => 60,
            'recipients' => collect($recipients)
                ->map(fn (InstitutionUser $recipient): array => [
                    'institution_id' => $recipient->institution['id'],
                ])
                ->all(),
        ];
    }

    private function jsonHeadersFor(InstitutionUser $institutionUser): array
    {
        return [
            ...AuthHelpers::createHeadersForInstitutionUser($institutionUser),
            'Accept' => 'application/json',
        ];
    }
}
