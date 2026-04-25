<?php

namespace Tests\Feature\Services\ExternalTranslationRequest;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\Project;
use App\Models\SubProject;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
use DomainException;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalTranslationRequestStateMachineTest extends TestCase
{
    private ExternalTranslationRequest $request;
    private ExternalTranslationRequestRecipient $recipient;
    private ExternalTranslationRequestStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = app(ExternalTranslationRequestStateMachine::class);

        $ownerInstitution = Institution::factory()->create();
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

        $this->request = ExternalTranslationRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'created_by_institution_user_id' => $ownerUser->id,
            'mode' => ExternalRequestMode::Parallel,
            'deadline_at' => now()->addDay(),
            'reaction_time_minutes' => null,
            'status' => ExternalRequestStatus::Active,
        ]);

        $this->recipient = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $this->request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 1,
            'status' => ExternalRequestRecipientStatus::Notified,
            'notified_at' => now(),
            'expires_at' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // acceptRecipient
    // -------------------------------------------------------------------------

    public function test_accept_transitions_notified_to_accepted(): void
    {
        $this->stateMachine->acceptRecipient($this->recipient, null, null);

        $fresh = $this->recipient->fresh();
        $this->assertSame(ExternalRequestRecipientStatus::Accepted, $fresh->status);
        $this->assertNotNull($fresh->responded_at);
    }

    public function test_accept_stores_proposed_price_and_comment(): void
    {
        $this->stateMachine->acceptRecipient($this->recipient, 99.99, 'Available');

        $fresh = $this->recipient->fresh();
        $this->assertSame(99.99, (float) $fresh->proposed_price);
        $this->assertSame('Available', $fresh->response_comment);
    }

    public function test_accept_on_terminal_recipient_throws_domain_exception(): void
    {
        $this->recipient->update(['status' => ExternalRequestRecipientStatus::Declined]);

        $this->expectException(DomainException::class);

        $this->stateMachine->acceptRecipient($this->recipient, null, null);
    }

    // -------------------------------------------------------------------------
    // declineRecipient
    // -------------------------------------------------------------------------

    public function test_decline_transitions_notified_to_declined(): void
    {
        $this->stateMachine->declineRecipient($this->recipient, 'No capacity');

        $fresh = $this->recipient->fresh();
        $this->assertSame(ExternalRequestRecipientStatus::Declined, $fresh->status);
        $this->assertSame('No capacity', $fresh->decline_comment);
        $this->assertNotNull($fresh->responded_at);
    }

    public function test_decline_parallel_does_not_activate_next_recipient(): void
    {
        $second = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $this->request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 2,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);

        $this->stateMachine->declineRecipient($this->recipient, 'No');

        $this->assertSame(ExternalRequestRecipientStatus::Pending, $second->fresh()->status);
    }

    public function test_decline_cascade_activates_exactly_next_pending_recipient(): void
    {
        Queue::fake();

        $this->request->update([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $this->recipient->update(['expires_at' => now()->addHour()]);

        $second = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $this->request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 2,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);
        $third = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $this->request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 3,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);

        $this->stateMachine->declineRecipient($this->recipient, 'No');

        $this->assertSame(ExternalRequestRecipientStatus::Notified, $second->fresh()->status);
        $this->assertSame(ExternalRequestRecipientStatus::Pending, $third->fresh()->status);
    }

    public function test_cascade_exhaustion_keeps_request_active(): void
    {
        Queue::fake();

        $this->request->update([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $this->recipient->update(['expires_at' => now()->addHour()]);

        $this->stateMachine->declineRecipient($this->recipient, 'No');

        $this->assertSame(ExternalRequestStatus::Active, $this->request->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // expireRecipient
    // -------------------------------------------------------------------------

    public function test_expire_transitions_notified_to_expired(): void
    {
        Queue::fake();

        $this->request->update([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
        $this->recipient->update(['expires_at' => now()->subMinute()]);

        $this->stateMachine->expireRecipient($this->recipient);

        $this->assertSame(ExternalRequestRecipientStatus::Expired, $this->recipient->fresh()->status);
    }

    public function test_expire_is_idempotent_on_already_expired_recipient(): void
    {
        $this->recipient->update([
            'status' => ExternalRequestRecipientStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);

        $this->stateMachine->expireRecipient($this->recipient);

        $this->assertSame(ExternalRequestRecipientStatus::Expired, $this->recipient->fresh()->status);
    }

    public function test_expire_no_ops_when_request_is_cancelled(): void
    {
        $this->request->update(['status' => ExternalRequestStatus::Cancelled]);
        $this->recipient->update(['expires_at' => now()->subMinute()]);

        $this->stateMachine->expireRecipient($this->recipient);

        $this->assertSame(ExternalRequestRecipientStatus::Notified, $this->recipient->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // selectRecipient
    // -------------------------------------------------------------------------

    public function test_select_transitions_accepted_to_selected_and_fulfills_request(): void
    {
        $this->recipient->update(['status' => ExternalRequestRecipientStatus::Accepted]);

        $this->stateMachine->selectRecipient($this->request, $this->recipient);

        $this->assertSame(ExternalRequestRecipientStatus::Selected, $this->recipient->fresh()->status);
        $this->assertSame(ExternalRequestStatus::Fulfilled, $this->request->fresh()->status);
    }

    public function test_select_writes_external_institution_id_to_assignment(): void
    {
        $this->recipient->update(['status' => ExternalRequestRecipientStatus::Accepted]);

        $this->stateMachine->selectRecipient($this->request, $this->recipient);

        $assignment = $this->request->assignment->fresh();
        $this->assertSame($this->recipient->institution_id, $assignment->external_institution_id);
    }

    public function test_select_price_priority_proposed_over_request_price(): void
    {
        $this->recipient->update([
            'status' => ExternalRequestRecipientStatus::Accepted,
            'proposed_price' => 50.00,
            'calculated_price' => 100.00,
        ]);
        $this->request->update(['price' => 75.00]);

        $this->stateMachine->selectRecipient($this->request, $this->recipient);

        $this->assertSame(50.0, (float) $this->request->assignment->fresh()->price);
    }

    public function test_select_falls_back_to_request_price_when_no_proposed(): void
    {
        $this->recipient->update([
            'status' => ExternalRequestRecipientStatus::Accepted,
            'proposed_price' => null,
            'calculated_price' => 100.00,
        ]);
        $this->request->update(['price' => 75.00]);

        $this->stateMachine->selectRecipient($this->request, $this->recipient);

        $this->assertSame(75.0, (float) $this->request->assignment->fresh()->price);
    }

    public function test_select_falls_back_to_calculated_price_when_no_overrides(): void
    {
        $this->recipient->update([
            'status' => ExternalRequestRecipientStatus::Accepted,
            'proposed_price' => null,
            'calculated_price' => 100.00,
        ]);
        $this->request->update(['price' => null]);

        $this->stateMachine->selectRecipient($this->request, $this->recipient);

        $this->assertSame(100.0, (float) $this->request->assignment->fresh()->price);
    }

    // -------------------------------------------------------------------------
    // cancelRequest
    // -------------------------------------------------------------------------

    public function test_cancel_transitions_active_to_cancelled(): void
    {
        $this->stateMachine->cancelRequest($this->request);

        $this->assertSame(ExternalRequestStatus::Cancelled, $this->request->fresh()->status);
    }

    public function test_cancel_expires_notified_and_pending_recipients(): void
    {
        $pending = ExternalTranslationRequestRecipient::factory()->create([
            'external_translation_request_id' => $this->request->id,
            'institution_id' => Institution::factory()->create()->id,
            'position' => 2,
            'status' => ExternalRequestRecipientStatus::Pending,
        ]);

        $this->stateMachine->cancelRequest($this->request);

        $this->assertSame(ExternalRequestRecipientStatus::Expired, $this->recipient->fresh()->status);
        $this->assertSame(ExternalRequestRecipientStatus::Expired, $pending->fresh()->status);
    }

    public function test_cancel_leaves_terminal_recipients_unchanged(): void
    {
        $this->recipient->update(['status' => ExternalRequestRecipientStatus::Declined]);

        $this->stateMachine->cancelRequest($this->request);

        $this->assertSame(ExternalRequestRecipientStatus::Declined, $this->recipient->fresh()->status);
    }
}
