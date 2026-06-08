<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestPriceMode;
use App\Enums\OutsourceRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use Tests\AuthHelpers;
use Tests\TestCase;

class OutsourceOfferControllerTest extends TestCase
{
    // --- index ---

    public function test_receiver_with_respond_privilege_can_list_their_offers(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/outsource-offers');

        // THEN
        $response->assertOk();
        $response->assertJsonPath('data.0.id', $offer->id);
        $response->assertJsonCount(1, 'data');
    }

    public function test_receiver_cannot_see_offers_belonging_to_other_institutions(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $this->createNotifiedRecipient($translationRequest, $partnerA);

        // WHEN — partnerB lists their offers (should see nothing)
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerB))
            ->getJson('/api/outsource-offers');

        // THEN
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_index_filters_by_assignment_id(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignmentA = $this->createAssignmentForOwner($ownerUser);
        $assignmentB = $this->createAssignmentForOwner($ownerUser);
        $requestA = $this->createTranslationRequest($assignmentA);
        $requestB = $this->createTranslationRequest($assignmentB);
        $offerA = $this->createNotifiedRecipient($requestA, $partnerUser);
        $this->createNotifiedRecipient($requestB, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/outsource-offers?assignment_id={$assignmentA->id}");

        // THEN
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $offerA->id);
    }

    public function test_index_filters_by_status(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignmentA = $this->createAssignmentForOwner($ownerUser);
        $assignmentB = $this->createAssignmentForOwner($ownerUser);
        $requestA = $this->createTranslationRequest($assignmentA);
        $requestB = $this->createTranslationRequest($assignmentB);
        $sentOffer = $this->createNotifiedRecipient($requestA, $partnerUser, ['status' => OutsourceOfferStatus::RequestSent]);
        $this->createNotifiedRecipient($requestB, $partnerUser, ['status' => OutsourceOfferStatus::RequestAccepted]);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/outsource-offers?status[]=' . OutsourceOfferStatus::RequestSent->value);

        // THEN
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $sentOffer->id);
    }

    public function test_user_without_respond_privilege_cannot_list_offers(): void
    {
        // GIVEN
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson('/api/outsource-offers');

        // THEN
        $response->assertForbidden();
    }

    // --- show ---

    public function test_receiver_can_show_their_offer(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/outsource-offers/{$offer->id}");

        // THEN
        $response->assertOk();
        $response->assertJsonPath('data.id', $offer->id);
        $response->assertJsonPath('data.outsource_request.id', $translationRequest->id);
    }

    public function test_receiver_cannot_show_another_institutions_offer(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerA = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $partnerB = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offerA = $this->createNotifiedRecipient($translationRequest, $partnerA);

        // WHEN — partnerB tries to access partnerA's offer
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerB))
            ->getJson("/api/outsource-offers/{$offerA->id}");

        // THEN — scope excludes it, 404
        $response->assertNotFound();
    }

    public function test_user_without_privilege_cannot_show_offer(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(); // no privileges
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/outsource-offers/{$offer->id}");

        // THEN
        $response->assertForbidden();
    }

    // --- accept ---

    public function test_partner_with_respond_privilege_can_accept_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser, ['price' => '50.000']);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/accept");

        // THEN
        $response->assertOk();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->fresh()->status);
    }

    public function test_partner_without_respond_privilege_cannot_accept(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/accept");

        // THEN
        $response->assertForbidden();
    }

    public function test_owner_institution_user_cannot_accept_their_own_offer(): void
    {
        // GIVEN — owner has no offer row in outsource_offers (scope would return nothing)
        $ownerUser = $this->createOwnerUser(PrivilegeKey::RespondOutsourceRequest);
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN — owner tries to accept partnerUser's offer
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/accept");

        // THEN — scope filters out the offer (institution_id mismatch), 404
        $response->assertNotFound();
    }

    public function test_accept_request_with_ask_for_price_requires_price(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'price_mode' => OutsourceRequestPriceMode::AskForPrice,
        ]);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN — ASK_FOR_PRICE accept without price
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/accept");

        // THEN
        $response->assertUnprocessable()->assertJsonValidationErrors('price');
    }

    public function test_accept_request_with_fixed_price_rejects_price(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment, [
            'price_mode' => OutsourceRequestPriceMode::FixedPrice,
            'price' => '100.000',
        ]);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN — FIXED_PRICE accept with price
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/accept", ['price' => 99.0]);

        // THEN
        $response->assertUnprocessable()->assertJsonValidationErrors('price');
    }

    // --- decline ---

    public function test_partner_with_respond_privilege_can_decline_request(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertOk();
        $this->assertSame(OutsourceOfferStatus::RequestDeclined, $offer->fresh()->status);
    }

    public function test_partner_without_respond_privilege_cannot_decline(): void
    {
        // GIVEN
        $ownerUser = $this->createOwnerUser();
        $partnerUser = $this->createPartnerUser(PrivilegeKey::ViewOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertForbidden();
    }

    public function test_owner_institution_user_cannot_decline_their_own_offer(): void
    {
        // GIVEN — owner's institution is not a recipient, scope filters them out
        $ownerUser = $this->createOwnerUser(PrivilegeKey::RespondOutsourceRequest);
        $partnerUser = $this->createPartnerUser(PrivilegeKey::RespondOutsourceRequest);
        $assignment = $this->createAssignmentForOwner($ownerUser);
        $translationRequest = $this->createTranslationRequest($assignment);
        $offer = $this->createNotifiedRecipient($translationRequest, $partnerUser);

        // WHEN
        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($ownerUser))
            ->postJson("/api/outsource-offers/{$offer->id}/decline", [
                'decline_comment' => 'Not available',
            ]);

        // THEN
        $response->assertNotFound();
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

    private function createAssignmentForOwner(
        InstitutionUser $ownerUser,
        array $overrides = [],
        ?string $typeClassifierValueId = null
    ): Assignment {
        $project = Project::factory()->create(array_filter([
            'institution_id' => $ownerUser->institution['id'],
            'type_classifier_value_id' => $typeClassifierValueId,
        ]));
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
}
