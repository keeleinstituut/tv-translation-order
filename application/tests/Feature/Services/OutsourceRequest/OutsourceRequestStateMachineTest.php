<?php

namespace Tests\Feature\Services\OutsourceRequest;

use App\Enums\OutsourceRequestMode;
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
        $offer = $this->createOffer(['status' => OutsourceOfferStatus::RequestSent]);

        // WHEN
        $stateMachine->acceptOffer($offer, 123.456, 'We can do this.');

        // THEN
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->status);
        $this->assertSame('123.456', $offer->proposed_price);
        $this->assertSame('We can do this.', $offer->response_comment);
        $this->assertNotNull($offer->responded_at);
    }

    public function test_accept_offer_rejects_proposed_price_when_request_has_fixed_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['fixed_price' => '100.000']);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot set proposed price when request has a fixed price.');

        // WHEN
        $stateMachine->acceptOffer($offer, 99.000, null);
    }

    public function test_accept_offer_allows_null_proposed_price_when_request_has_fixed_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['fixed_price' => '100.000']);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'proposed_price' => '100.000',
        ]);

        // WHEN
        $stateMachine->acceptOffer($offer, null, 'Confirmed.');

        // THEN — proposed_price stays at the pre-populated fixed_price value
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->status);
        $this->assertSame('100.000', $offer->proposed_price);
    }

    public function test_accept_offer_rejects_non_notified_offer(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $offer = $this->createOffer(['status' => OutsourceOfferStatus::RequestPending]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Recipient is not in REQUEST_SENT state.');

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
            'status' => OutsourceOfferStatus::RequestSent,
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
            'status' => OutsourceOfferStatus::RequestSent,
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
            'status' => OutsourceOfferStatus::RequestSent,
        ]);
        $next = $this->createOffer([
            'outsource_request_id' => $request->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::RequestPending,
            'expires_at' => null,
        ]);

        // WHEN
        $stateMachine->declineOffer($notified, 'No capacity.');

        // THEN
        $notified->refresh();
        $next->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestDeclined, $notified->status);
        $this->assertSame('No capacity.', $notified->decline_comment);
        $this->assertNotNull($notified->responded_at);
        $this->assertSame(OutsourceOfferStatus::RequestSent, $next->status);
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
            'status' => OutsourceOfferStatus::RequestSent,
            'expires_at' => now()->subMinute(),
        ]);
        $next = $this->createOffer([
            'outsource_request_id' => $request->id,
            'position' => 2,
            'status' => OutsourceOfferStatus::RequestPending,
            'expires_at' => null,
        ]);

        // WHEN
        $stateMachine->expireOffer($notified);

        // THEN
        $this->assertSame(OutsourceOfferStatus::RequestExpired, $notified->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestSent, $next->fresh()->status);
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
            'status' => OutsourceOfferStatus::RequestSent,
            'expires_at' => now()->addHour(),
        ]);
        $acceptedOffer = $this->createOffer(['status' => OutsourceOfferStatus::RequestAccepted]);
        $inactiveRequest = $this->createRequest(['status' => OutsourceRequestStatus::Cancelled]);
        $inactiveOffer = $this->createOffer([
            'outsource_request_id' => $inactiveRequest->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'expires_at' => now()->subMinute(),
        ]);

        // WHEN
        $stateMachine->expireOffer($futureOffer);
        $stateMachine->expireOffer($acceptedOffer);
        $stateMachine->expireOffer($inactiveOffer);

        // THEN
        $this->assertSame(OutsourceOfferStatus::RequestSent, $futureOffer->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $acceptedOffer->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestSent, $inactiveOffer->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_select_offer_fulfills_request_rejects_other_in_play_offers_and_updates_assignment(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['fixed_price' => '555.000']);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'proposed_price' => '321.123',
        ]);
        $loser = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
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
        $this->assertSame(OutsourceOfferStatus::OfferAccepted, $winner->status);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $loser->status);
        $this->assertSame('Price was higher.', $loser->rejection_comment);
        $this->assertEquals(321.12, $assignment->price);
    }

    public function test_select_offer_uses_request_price_when_offer_has_no_proposed_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['fixed_price' => '222.222']);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
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
        $request = $this->createRequest(['fixed_price' => null]);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'proposed_price' => null,
            'calculated_price' => '111.111',
        ]);

        // WHEN
        $stateMachine->selectOffer($request, $winner, []);

        // THEN
        $this->assertEquals(111.11, $request->assignment->fresh()->price);
    }

    public function test_cancel_request_leaves_offers_unchanged(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest();
        $pending = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);
        $notified = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
        ]);
        $accepted = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
        ]);
        $declined = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestDeclined,
        ]);
        $rejected = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::OfferDeclined,
        ]);

        // WHEN
        $stateMachine->cancelRequest($request, 'Test cancellation.');

        // THEN
        $this->assertSame(OutsourceRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestPending, $pending->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestSent, $notified->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $accepted->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestDeclined, $declined->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $rejected->fresh()->status);
    }

    private function createRequest(array $overrides = []): OutsourceRequest
    {
        return OutsourceRequest::factory()->create(array_merge([
            'assignment_id' => $this->createAssignment()->id,
            'mode' => OutsourceRequestMode::Parallel,
            'reaction_time_minutes' => 24 * 60,
            'status' => OutsourceRequestStatus::Active,
        ], $overrides));
    }

    private function createCascadeRequest(array $overrides = []): OutsourceRequest
    {
        return $this->createRequest(array_merge([
            'mode' => OutsourceRequestMode::Cascade,
            'reaction_time_minutes' => 60,
        ], $overrides));
    }

    private function createOffer(array $overrides = []): OutsourceOffer
    {
        return OutsourceOffer::factory()->create(array_merge([
            'outsource_request_id' => $this->createRequest()->id,
            'status' => OutsourceOfferStatus::RequestSent,
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
