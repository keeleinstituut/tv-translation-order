<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Support\Facades\Queue;
use Tests\AuthHelpers;
use Tests\TestCase;

class ExternalTranslationRequestControllerTest extends TestCase
{
    public function test_partner_detail_hides_files_and_prices_when_flags_are_disabled(): void
    {
        [$request, $recipient, $partnerInstitution] = $this->createExternalRequestScenario([
            'include_source_files' => false,
            'include_price' => false,
            'price' => 123.456,
        ], [
            'calculated_price' => 100.000,
            'proposed_price' => 110.000,
        ]);

        $request->addMediaFromString('source content')
            ->usingFileName('source.txt')
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);

        $response = $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::ViewExternalTranslationRequest,
        ]))->getJson("/api/external-translation-request-recipients/{$recipient->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayNotHasKey('request_files', $data);
        $this->assertArrayNotHasKey('calculated_price', $data);
        $this->assertArrayNotHasKey('proposed_price', $data);
        $this->assertArrayNotHasKey('effective_price', $data);
    }

    public function test_partner_cannot_download_request_files_when_source_files_are_disabled(): void
    {
        [$request, , $partnerInstitution] = $this->createExternalRequestScenario([
            'include_source_files' => false,
        ]);

        $media = $request->addMediaFromString('source content')
            ->usingFileName('source.txt')
            ->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);

        $query = http_build_query([
            'collection' => ExternalTranslationRequest::REQUEST_FILES_COLLECTION,
            'reference_object_id' => $request->id,
            'reference_object_type' => 'external_translation_request',
            'id' => $media->id,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::ViewExternalTranslationRequest,
        ]))->getJson("/api/media/download?{$query}")
            ->assertForbidden();
    }

    public function test_cascade_recipient_cannot_accept_after_expiry(): void
    {
        [$request, $recipient, $partnerInstitution] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ], [
            'expires_at' => now()->subMinute(),
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$recipient->id}/accept")
            ->assertUnprocessable();

        $this->assertSame(
            ExternalRequestRecipientStatus::Notified,
            $recipient->fresh()->status
        );
        $this->assertSame(ExternalRequestStatus::Active, $request->fresh()->status);
    }

    public function test_parallel_recipient_cannot_decline_after_deadline(): void
    {
        [$request, $recipient, $partnerInstitution] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Parallel,
            'deadline_at' => now()->subMinute(),
        ], [
            'expires_at' => null,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$recipient->id}/decline", [
            'decline_comment' => 'Too late',
        ])->assertUnprocessable();

        $this->assertSame(
            ExternalRequestRecipientStatus::Notified,
            $recipient->fresh()->status
        );
        $this->assertSame(ExternalRequestStatus::Active, $request->fresh()->status);
    }

    public function test_cascade_decline_activates_exactly_one_next_pending_recipient(): void
    {
        Queue::fake();

        [$request, $firstRecipient, $firstPartnerInstitution] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ], [
            'position' => 1,
            'expires_at' => now()->addHour(),
        ]);

        $secondRecipient = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 2,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);
        $thirdRecipient = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 3,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($firstPartnerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$firstRecipient->id}/decline", [
            'decline_comment' => 'No capacity',
        ])->assertOk();

        $this->assertSame(ExternalRequestRecipientStatus::Declined, $firstRecipient->fresh()->status);
        $this->assertSame(ExternalRequestRecipientStatus::Notified, $secondRecipient->fresh()->status);
        $this->assertSame(ExternalRequestRecipientStatus::Pending, $thirdRecipient->fresh()->status);
        $this->assertSame(1, $request->recipients()->where('status', ExternalRequestRecipientStatus::Notified)->count());
    }

    public function test_cancel_expires_open_recipients_and_makes_them_non_actionable(): void
    {
        [$request, $notifiedRecipient, $partnerInstitution, $ownerInstitution] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);

        $pendingRecipient = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 2,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);

        $this->prepareAuthorizedRequest($this->partnerToken($ownerInstitution, [
            PrivilegeKey::ManageExternalTranslationRequest,
        ]))->postJson("/api/external-translation-requests/{$request->id}/cancel")
            ->assertOk();

        $this->assertSame(ExternalRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(ExternalRequestRecipientStatus::Expired, $notifiedRecipient->fresh()->status);
        $this->assertSame(ExternalRequestRecipientStatus::Expired, $pendingRecipient->fresh()->status);

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$notifiedRecipient->id}/accept")
            ->assertUnprocessable();
    }

    private function createExternalRequestScenario(array $requestOverrides = [], array $recipientOverrides = []): array
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

        $request = ExternalTranslationRequest::factory()->create(array_merge([
            'assignment_id' => $assignment->id,
            'created_by_institution_user_id' => $ownerUser->id,
            'mode' => ExternalRequestMode::Parallel,
            'deadline_at' => now()->addDay(),
            'reaction_time_minutes' => null,
            'include_source_files' => true,
            'include_price' => true,
            'status' => ExternalRequestStatus::Active,
        ], $requestOverrides));

        $recipient = ExternalTranslationRequestRecipient::factory()->create(array_merge([
            'external_translation_request_id' => $request->id,
            'institution_id' => $partnerInstitution->id,
            'position' => 1,
            'status' => ExternalRequestRecipientStatus::Notified,
            'notified_at' => now(),
            'expires_at' => $request->isCascade() ? now()->addHour() : null,
        ], $recipientOverrides));

        return [$request, $recipient, $partnerInstitution, $ownerInstitution];
    }

    /**
     * @param array<PrivilegeKey> $privileges
     */
    private function partnerToken(Institution $institution, array $privileges): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => collect($privileges)->map(fn (PrivilegeKey $privilege) => $privilege->value)->all(),
        ]);
    }
}
