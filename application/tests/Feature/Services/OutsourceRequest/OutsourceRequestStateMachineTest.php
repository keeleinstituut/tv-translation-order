<?php

namespace Tests\Feature\Services\OutsourceRequest;

use App\Enums\ExternalRequestMode;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Jobs\ExpireOutsourceOfferJob;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\SubProject;
use App\Services\OutsourceRequest\OutsourceRequestStateMachine;
use DomainException;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutsourceRequestStateMachineTest extends TestCase
{
    public function test_accept_offer_persists_response_details(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $offer = $this->createOffer(['status' => OutsourceOfferStatus::Notified]);

        // WHEN
        $stateMachine->acceptOffer($offer, 123.456, 'We can do this.');

        // THEN
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::Accepted, $offer->status);
        $this->assertSame('123.456', $offer->proposed_price);
        $this->assertSame('We can do this.', $offer->response_comment);
        $this->assertNotNull($offer->responded_at);
    }

    public function test_accept_offer_rejects_non_notified_offer(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $offer = $this->createOffer(['status' => OutsourceOfferStatus::Pending]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Recipient is not in NOTIFIED state.');

        // WHEN
        $stateMachine->acceptOffer($offer, null, null);
    }

    public function test_accept_offer_rejects_inactive_request(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['status' => OutsourceRequestStatus::Cancelled]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Notified,
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request is not ACTIVE.');

        // WHEN
        $stateMachine->acceptOffer($offer, null, null);
    }

    public function test_accept_offer_rejects_expired_response_window(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createCascadeRequest();
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Notified,
            'expires_at' => now()->subMinute(),
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Recipient response deadline has passed.');

        // WHEN
        $stateMachine->acceptOffer($offer, null, null);
    }

    public function test_decline_offer_activates_next_cascade_offer(): void
    {
        // GIVEN
        Queue::fake();
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createCascadeRequest();
        $notified = $this->createOffer([
            'outsource_request_id' => $request->id,
            'position' => 1,
            'status' => OutsourceOfferStatus::Notified,
        ]);
        $next = $this->createOffer([
            'outsource_request_id' => $request->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::Pending,
            'expires_at' => null,
        ]);

        // WHEN
        $stateMachine->declineOffer($notified, 'No capacity.');

        // THEN
        $notified->refresh();
        $next->refresh();
        $this->assertSame(OutsourceOfferStatus::Declined, $notified->status);
        $this->assertSame('No capacity.', $notified->decline_comment);
        $this->assertNotNull($notified->responded_at);
        $this->assertSame(OutsourceOfferStatus::Notified, $next->status);
        $this->assertNotNull($next->notified_at);
        $this->assertNotNull($next->expires_at);
        Queue::assertPushed(
            ExpireOutsourceOfferJob::class,
            fn (ExpireOutsourceOfferJob $job) => $job->recipientId === $next->id
        );
    }

    public function test_expire_offer_activates_next_cascade_offer_when_deadline_passed(): void
    {
        // GIVEN
        Queue::fake();
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createCascadeRequest();
        $notified = $this->createOffer([
            'outsource_request_id' => $request->id,
            'position' => 1,
            'status' => OutsourceOfferStatus::Notified,
            'expires_at' => now()->subMinute(),
        ]);
        $next = $this->createOffer([
            'outsource_request_id' => $request->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::Pending,
            'expires_at' => null,
        ]);

        // WHEN
        $stateMachine->expireOffer($notified);

        // THEN
        $this->assertSame(OutsourceOfferStatus::Expired, $notified->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Notified, $next->fresh()->status);
        Queue::assertPushed(
            ExpireOutsourceOfferJob::class,
            fn (ExpireOutsourceOfferJob $job) => $job->recipientId === $next->id
        );
    }

    public function test_expire_offer_is_noop_when_not_actionable(): void
    {
        // GIVEN
        Queue::fake();
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $futureOffer = $this->createOffer([
            'status' => OutsourceOfferStatus::Notified,
            'expires_at' => now()->addHour(),
        ]);
        $acceptedOffer = $this->createOffer(['status' => OutsourceOfferStatus::Accepted]);
        $inactiveRequest = $this->createRequest(['status' => OutsourceRequestStatus::Cancelled]);
        $inactiveOffer = $this->createOffer([
            'outsource_request_id' => $inactiveRequest->id,
            'status' => OutsourceOfferStatus::Notified,
            'expires_at' => now()->subMinute(),
        ]);

        // WHEN
        $stateMachine->expireOffer($futureOffer);
        $stateMachine->expireOffer($acceptedOffer);
        $stateMachine->expireOffer($inactiveOffer);

        // THEN
        $this->assertSame(OutsourceOfferStatus::Notified, $futureOffer->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Accepted, $acceptedOffer->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Notified, $inactiveOffer->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_select_offer_fulfills_request_rejects_other_in_play_offers_and_updates_assignment(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price' => '555.000']);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Accepted,
            'proposed_price' => '321.123',
        ]);
        $loser = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Accepted,
        ]);

        // WHEN
        $stateMachine->selectOffer($request, $winner, [
            $loser->id => 'Price was higher.',
        ]);

        // THEN
        $request->refresh();
        $winner->refresh();
        $loser->refresh();
        $assignment = $request->assignment->fresh();
        $this->assertSame(OutsourceRequestStatus::Fulfilled, $request->status);
        $this->assertSame(OutsourceOfferStatus::Selected, $winner->status);
        $this->assertSame(OutsourceOfferStatus::Rejected, $loser->status);
        $this->assertSame('Price was higher.', $loser->rejection_comment);
        $this->assertEquals(321.12, $assignment->price);
    }

    public function test_select_offer_uses_request_price_when_offer_has_no_proposed_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price' => '222.222']);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Accepted,
            'proposed_price' => null,
            'calculated_price' => '111.111',
        ]);

        // WHEN
        $stateMachine->selectOffer($request, $winner, []);

        // THEN
        $this->assertEquals(222.22, $request->assignment->fresh()->price);
    }

    public function test_select_offer_uses_calculated_price_when_offer_and_request_price_are_empty(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price' => null]);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Accepted,
            'proposed_price' => null,
            'calculated_price' => '111.111',
        ]);

        // WHEN
        $stateMachine->selectOffer($request, $winner, []);

        // THEN
        $this->assertEquals(111.11, $request->assignment->fresh()->price);
    }

    public function test_cancel_request_expires_only_pending_and_notified_offers(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest();
        $pending = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Pending,
        ]);
        $notified = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Notified,
        ]);
        $accepted = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Accepted,
        ]);
        $declined = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Declined,
        ]);
        $rejected = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::Rejected,
        ]);

        // WHEN
        $stateMachine->cancelRequest($request);

        // THEN
        $this->assertSame(OutsourceRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Expired, $pending->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Expired, $notified->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Accepted, $accepted->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Declined, $declined->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::Rejected, $rejected->fresh()->status);
    }

    private function createRequest(array $overrides = []): OutsourceRequest
    {
        return OutsourceRequest::factory()->create(array_merge([
            'assignment_id' => $this->createAssignment()->id,
            'mode' => ExternalRequestMode::Parallel,
            'deadline_at' => now()->addDay(),
            'status' => OutsourceRequestStatus::Active,
        ], $overrides));
    }

    private function createCascadeRequest(array $overrides = []): OutsourceRequest
    {
        return $this->createRequest(array_merge([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ], $overrides));
    }

    private function createOffer(array $overrides = []): OutsourceOffer
    {
        return OutsourceOffer::factory()->create(array_merge([
            'outsource_request_id' => $this->createRequest()->id,
            'status' => OutsourceOfferStatus::Notified,
            'expires_at' => now()->addHour(),
        ], $overrides));
    }

    private function createAssignment(): Assignment
    {
        $project = Project::factory()->create();
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        return Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);
    }
}
