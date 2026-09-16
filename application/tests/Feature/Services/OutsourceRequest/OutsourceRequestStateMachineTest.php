<?php

namespace Tests\Feature\Services\OutsourceRequest;

use App\Enums\OutsourceRequestMode;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestPriceMode;
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
    // --- acceptOffer: ASK_FOR_PRICE ---

    public function test_accept_offer_with_ask_for_price_mode_persists_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::AskForPrice]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => null,
        ]);

        // WHEN
        $stateMachine->acceptOffer($offer, 123.456, 'We can do this.');

        // THEN
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->status);
        $this->assertEqualsWithDelta(123.456, $offer->price, 0.0001);
        $this->assertSame('We can do this.', $offer->response_comment);
        $this->assertNotNull($offer->responded_at);
    }

    public function test_accept_offer_with_ask_for_price_mode_requires_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::AskForPrice]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Price is required when price mode is ASK_FOR_PRICE.');

        // WHEN
        $stateMachine->acceptOffer($offer, null, null);
    }

    // --- acceptOffer: FIXED_PRICE ---

    public function test_accept_offer_with_fixed_price_mode_rejects_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest([
            'price_mode' => OutsourceRequestPriceMode::FixedPrice,
            'price' => '100.000',
        ]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => '100.000',
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot set price when price mode is FIXED_PRICE.');

        // WHEN
        $stateMachine->acceptOffer($offer, 99.000, null);
    }

    public function test_accept_offer_with_fixed_price_mode_allows_null_price(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest([
            'price_mode' => OutsourceRequestPriceMode::FixedPrice,
            'price' => '100.000',
        ]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => '100.000',
        ]);

        // WHEN
        $stateMachine->acceptOffer($offer, null, 'Confirmed.');

        // THEN — price stays at the pre-populated fixed value
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->status);
        $this->assertEqualsWithDelta(100.0, $offer->price, 0.0001);
    }

    // --- acceptOffer: PRICELIST_BASED ---

    public function test_accept_offer_with_pricelist_based_and_null_offer_price_requires_price(): void
    {
        // GIVEN — calculator returned null at creation time
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::PriceListBased]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => null,
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Price is required when the pricelist-based price is unavailable.');

        // WHEN
        $stateMachine->acceptOffer($offer, null, null);
    }

    public function test_accept_offer_with_pricelist_based_and_null_offer_price_persists_supplied_price(): void
    {
        // GIVEN — calculator returned null at creation time, partner now supplies their price
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::PriceListBased]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => null,
        ]);

        // WHEN
        $stateMachine->acceptOffer($offer, 50.000, null);

        // THEN
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->status);
        $this->assertEqualsWithDelta(50.0, $offer->price, 0.0001);
    }

    public function test_accept_offer_with_pricelist_based_and_existing_offer_price_rejects_override(): void
    {
        // GIVEN — calculator already set a price at creation time
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::PriceListBased]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => '100.000',
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot override price when a pricelist-based price is already set.');

        // WHEN
        $stateMachine->acceptOffer($offer, 50.000, null);
    }

    public function test_accept_offer_with_pricelist_based_and_existing_offer_price_allows_null(): void
    {
        // GIVEN — calculator set a price; no override needed
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::PriceListBased]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'price' => '100.000',
        ]);

        // WHEN
        $stateMachine->acceptOffer($offer, null, 'Looks good.');

        // THEN — price unchanged
        $offer->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestAccepted, $offer->status);
        $this->assertEqualsWithDelta(100.0, $offer->price, 0.0001);
    }

    // --- acceptOffer: generic guards ---

    public function test_accept_offer_rejects_non_notified_offer(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::AskForPrice]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestPending,
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Recipient is not in REQUEST_SENT state.');

        // WHEN
        $stateMachine->acceptOffer($offer, 1.0, null);
    }

    public function test_accept_offer_rejects_inactive_request(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest([
            'status' => OutsourceRequestStatus::Cancelled,
            'price_mode' => OutsourceRequestPriceMode::AskForPrice,
        ]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Request is not ACTIVE.');

        // WHEN
        $stateMachine->acceptOffer($offer, 1.0, null);
    }

    public function test_accept_offer_rejects_expired_response_window(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createCascadeRequest(['price_mode' => OutsourceRequestPriceMode::AskForPrice]);
        $offer = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestSent,
            'expires_at' => now()->subMinute(),
        ]);

        // THEN
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Recipient response deadline has passed.');

        // WHEN
        $stateMachine->acceptOffer($offer, 1.0, null);
    }

    // --- declineOffer ---

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

    public function test_decline_offer_activates_next_cascade_offer_with_null_reaction_time_minutes(): void
    {
        // GIVEN
        Queue::fake();
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createCascadeRequest(['reaction_time_minutes' => null]);
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
        $next->refresh();
        $this->assertSame(OutsourceOfferStatus::RequestSent, $next->status);
        $this->assertNotNull($next->notified_at);
        $this->assertNull($next->expires_at);
        Queue::assertPushed(
            ExpireOutsourceOfferJob::class,
            fn (ExpireOutsourceOfferJob $job) => $job->recipientId === $next->id
        );
    }

    // --- expireOffer ---

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

    // --- selectOffer ---

    public function test_select_offer_fulfills_request_rejects_other_in_play_offers_and_updates_assignment(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::AskForPrice]);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'price' => '321.123',
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
        $this->assertEqualsWithDelta(321.123, $request->price, 0.0001);
        $this->assertSame(OutsourceOfferStatus::OfferAccepted, $winner->status);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $loser->status);
        $this->assertSame('Price was higher.', $loser->rejection_comment);
        $this->assertEqualsWithDelta(321.12, $assignment->price, 0.005);
    }

    public function test_select_offer_writes_offer_price_to_both_request_and_assignment(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::PriceListBased]);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'price' => '111.111',
        ]);

        // WHEN
        $stateMachine->selectOffer($request, $winner, []);

        // THEN
        $request->refresh();
        $this->assertEqualsWithDelta(111.111, $request->price, 0.0001);
        $this->assertEqualsWithDelta(111.11, $request->assignment->fresh()->price, 0.005);
    }

    public function test_select_offer_with_null_price_sets_null_on_both_request_and_assignment(): void
    {
        // GIVEN
        $stateMachine = app(OutsourceRequestStateMachine::class);
        $request = $this->createRequest(['price_mode' => OutsourceRequestPriceMode::PriceListBased]);
        $winner = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::RequestAccepted,
            'price' => null,
        ]);

        // WHEN
        $stateMachine->selectOffer($request, $winner, []);

        // THEN
        $request->refresh();
        $this->assertNull($request->price);
        $this->assertNull($request->assignment->fresh()->price);
    }

    // --- cancelRequest ---

    public function test_cancel_request_transitions_active_offers_to_request_cancelled(): void
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
        $offerDeclined = $this->createOffer([
            'outsource_request_id' => $request->id,
            'status' => OutsourceOfferStatus::OfferDeclined,
        ]);

        // WHEN
        $stateMachine->cancelRequest($request, 'Test cancellation.');

        // THEN
        $this->assertSame(OutsourceRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestPending, $pending->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestCancelled, $notified->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestCancelled, $accepted->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::RequestCancelled, $declined->fresh()->status);
        $this->assertSame(OutsourceOfferStatus::OfferDeclined, $offerDeclined->fresh()->status);
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
