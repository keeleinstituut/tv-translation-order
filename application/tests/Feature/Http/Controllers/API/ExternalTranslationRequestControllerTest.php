<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Enums\InstitutionType;
use App\Enums\PrivilegeKey;
use App\Jobs\ExpireExternalTranslationRequestRecipientJob;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\InstitutionPartner;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
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

    // -------------------------------------------------------------------------
    // Store — POST /api/external-translation-requests
    // -------------------------------------------------------------------------

    public function test_store_parallel_happy_path_all_recipients_notified(): void
    {
        Queue::fake();
        [$ownerInstitution, $ownerUser, $partnerInstitution, $assignment] = $this->createStoreScenario();

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => now()->addDay()->toIso8601String(),
                'recipients' => [['institution_id' => $partnerInstitution->id]],
            ])
            ->assertCreated();

        $this->assertSame(
            1,
            ExternalTranslationRequestRecipient::where('status', ExternalRequestRecipientStatus::Notified)->count()
        );
    }

    public function test_store_cascade_happy_path_only_first_recipient_notified(): void
    {
        Queue::fake();
        [$ownerInstitution, $ownerUser, $firstPartner, $assignment] = $this->createStoreScenario();

        $secondPartner = Institution::factory()->create();
        InstitutionPartner::factory()->create([
            'institution_id' => $ownerInstitution->id,
            'partner_institution_id' => $secondPartner->id,
        ]);

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Cascade->value,
                'reaction_time_minutes' => 60,
                'recipients' => [
                    ['institution_id' => $firstPartner->id],
                    ['institution_id' => $secondPartner->id],
                ],
            ])
            ->assertCreated();

        $recipients = ExternalTranslationRequest::first()->recipients()->orderBy('position')->get();
        $this->assertSame(ExternalRequestRecipientStatus::Notified, $recipients[0]->status);
        $this->assertSame(ExternalRequestRecipientStatus::Pending, $recipients[1]->status);
    }

    public function test_store_422_when_assignment_has_assigned_vendor(): void
    {
        [$ownerInstitution, $ownerUser, $partnerInstitution, $assignment] = $this->createStoreScenario();
        $vendor = Vendor::factory()->create();
        $assignment->update(['assigned_vendor_id' => $vendor->id]);

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => now()->addDay()->toIso8601String(),
                'recipients' => [['institution_id' => $partnerInstitution->id]],
            ])
            ->assertUnprocessable();
    }

    public function test_store_422_when_assignment_already_shared(): void
    {
        [$ownerInstitution, $ownerUser, $partnerInstitution, $assignment] = $this->createStoreScenario();
        $assignment->update(['external_institution_id' => $partnerInstitution->id]);

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => now()->addDay()->toIso8601String(),
                'recipients' => [['institution_id' => $partnerInstitution->id]],
            ])
            ->assertUnprocessable();
    }

    public function test_store_422_when_recipient_not_a_partner(): void
    {
        [$ownerInstitution, $ownerUser, , $assignment] = $this->createStoreScenario();
        $nonPartner = Institution::factory()->create();

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => now()->addDay()->toIso8601String(),
                'recipients' => [['institution_id' => $nonPartner->id]],
            ])
            ->assertUnprocessable();
    }

    public function test_store_403_when_sender_is_translation_agency(): void
    {
        [$ownerInstitution, $ownerUser, $partnerInstitution, $assignment] = $this->createStoreScenario();
        $ownerInstitution->update(['institution_type' => InstitutionType::TranslationAgency]);

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => now()->addDay()->toIso8601String(),
                'recipients' => [['institution_id' => $partnerInstitution->id]],
            ])
            ->assertForbidden();
    }

    public function test_store_422_when_active_request_already_exists_for_assignment(): void
    {
        [$ownerInstitution, $ownerUser, $partnerInstitution, $assignment] = $this->createStoreScenario();

        ExternalTranslationRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'created_by_institution_user_id' => $ownerUser->id,
            'status' => ExternalRequestStatus::Active,
        ]);

        $this->prepareAuthorizedRequest($this->ownerStoreToken($ownerInstitution, $ownerUser))
            ->postJson('/api/external-translation-requests', [
                'assignment_id' => $assignment->id,
                'mode' => ExternalRequestMode::Parallel->value,
                'deadline_at' => now()->addDay()->toIso8601String(),
                'recipients' => [['institution_id' => $partnerInstitution->id]],
            ])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // Accept — POST /api/external-translation-request-recipients/{id}/accept
    // -------------------------------------------------------------------------

    public function test_accept_stores_proposed_price_and_response_comment(): void
    {
        [, $recipient, $partnerInstitution] = $this->createExternalRequestScenario();

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$recipient->id}/accept", [
            'proposed_price' => 99.99,
            'response_comment' => 'Happy to help',
        ])->assertOk();

        $fresh = $recipient->fresh();
        $this->assertSame(ExternalRequestRecipientStatus::Accepted, $fresh->status);
        $this->assertSame(99.99, (float) $fresh->proposed_price);
        $this->assertSame('Happy to help', $fresh->response_comment);
    }

    public function test_decline_requires_decline_comment(): void
    {
        [, $recipient, $partnerInstitution] = $this->createExternalRequestScenario();

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$recipient->id}/decline", [])
            ->assertUnprocessable();
    }

    public function test_403_accept_without_respond_privilege(): void
    {
        [, $recipient, $partnerInstitution] = $this->createExternalRequestScenario();

        $this->prepareAuthorizedRequest($this->partnerToken($partnerInstitution, [
            PrivilegeKey::ViewExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$recipient->id}/accept")
            ->assertForbidden();
    }

    public function test_403_decline_from_owner_institution(): void
    {
        [, $recipient, , $ownerInstitution] = $this->createExternalRequestScenario();

        // Owner can see the recipient (scope allows via project ownership) but cannot decline it
        $this->prepareAuthorizedRequest($this->partnerToken($ownerInstitution, [
            PrivilegeKey::RespondExternalTranslationRequest,
        ]))->postJson("/api/external-translation-request-recipients/{$recipient->id}/decline", [
            'decline_comment' => 'Not my recipient',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Select — POST /api/external-translation-requests/{id}/select
    // -------------------------------------------------------------------------

    public function test_select_422_if_recipient_not_accepted(): void
    {
        [$request, $recipient, , $ownerInstitution] = $this->createExternalRequestScenario();

        $this->prepareAuthorizedRequest($this->partnerToken($ownerInstitution, [
            PrivilegeKey::ManageExternalTranslationRequest,
        ]))->postJson("/api/external-translation-requests/{$request->id}/select", [
            'recipient_id' => $recipient->id,
        ])->assertUnprocessable();
    }

    public function test_select_422_if_request_not_active(): void
    {
        [$request, $recipient, , $ownerInstitution] = $this->createExternalRequestScenario();
        $recipient->update(['status' => ExternalRequestRecipientStatus::Accepted]);
        $request->update(['status' => ExternalRequestStatus::Cancelled]);

        $this->prepareAuthorizedRequest($this->partnerToken($ownerInstitution, [
            PrivilegeKey::ManageExternalTranslationRequest,
        ]))->postJson("/api/external-translation-requests/{$request->id}/select", [
            'recipient_id' => $recipient->id,
        ])->assertUnprocessable();
    }

    public function test_select_writes_external_institution_id_to_assignment(): void
    {
        [$request, $recipient, $partnerInstitution, $ownerInstitution] = $this->createExternalRequestScenario();
        $recipient->update(['status' => ExternalRequestRecipientStatus::Accepted]);

        $this->prepareAuthorizedRequest($this->partnerToken($ownerInstitution, [
            PrivilegeKey::ManageExternalTranslationRequest,
        ]))->postJson("/api/external-translation-requests/{$request->id}/select", [
            'recipient_id' => $recipient->id,
        ])->assertOk();

        $this->assertSame($partnerInstitution->id, $request->assignment->fresh()->external_institution_id);
        $this->assertSame(ExternalRequestStatus::Fulfilled, $request->fresh()->status);
    }

    public function test_403_select_without_manage_privilege(): void
    {
        [$request, $recipient, , $ownerInstitution] = $this->createExternalRequestScenario();
        $recipient->update(['status' => ExternalRequestRecipientStatus::Accepted]);

        $this->prepareAuthorizedRequest($this->partnerToken($ownerInstitution, [
            PrivilegeKey::ViewExternalTranslationRequest,
        ]))->postJson("/api/external-translation-requests/{$request->id}/select", [
            'recipient_id' => $recipient->id,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Jobs
    // -------------------------------------------------------------------------

    public function test_expire_job_no_ops_on_already_terminal_recipient(): void
    {
        [, $recipient] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ], [
            'status' => ExternalRequestRecipientStatus::Declined,
            'expires_at' => now()->subHour(),
        ]);

        (new ExpireExternalTranslationRequestRecipientJob($recipient->id))
            ->handle(app(ExternalTranslationRequestStateMachine::class));

        $this->assertSame(ExternalRequestRecipientStatus::Declined, $recipient->fresh()->status);
    }

    public function test_expire_job_no_ops_when_request_is_cancelled(): void
    {
        [$request, $recipient] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
            'status' => ExternalRequestStatus::Cancelled,
        ], [
            'expires_at' => now()->subHour(),
        ]);

        (new ExpireExternalTranslationRequestRecipientJob($recipient->id))
            ->handle(app(ExternalTranslationRequestStateMachine::class));

        $this->assertSame(ExternalRequestRecipientStatus::Notified, $recipient->fresh()->status);
    }

    public function test_sweeper_expires_missed_notified_recipients(): void
    {
        [, $recipient] = $this->createExternalRequestScenario([
            'mode' => ExternalRequestMode::Parallel,
            'deadline_at' => now()->subMinute(),
        ], [
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('app:sweep-expired-external-translation-request-recipients')->assertSuccessful();

        $this->assertSame(ExternalRequestRecipientStatus::Expired, $recipient->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Builds a minimal store scenario: owner institution + user + partner + InstitutionPartner + assignment.
     *
     * @return array{Institution, InstitutionUser, Institution, Assignment}
     */
    private function createStoreScenario(): array
    {
        $ownerInstitution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();
        $ownerUser = InstitutionUser::factory()
            ->setInstitution(['id' => $ownerInstitution->id, 'name' => $ownerInstitution->name])
            ->create();

        InstitutionPartner::factory()->create([
            'institution_id' => $ownerInstitution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

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

        return [$ownerInstitution, $ownerUser, $partnerInstitution, $assignment];
    }

    private function ownerStoreToken(Institution $institution, InstitutionUser $user): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'institutionUserId' => $user->id,
            'privileges' => [PrivilegeKey::ManageExternalTranslationRequest->value],
        ]);
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
