<?php

namespace App\Services\OutsourceRequest;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Jobs\ExpireOutsourceOfferJob;
use App\Models\OutsourceRequest;
use App\Models\OutsourceOffer;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

readonly class OutsourceRequestStateMachine
{
    public function acceptOffer(
        OutsourceOffer $recipient,
        ?float         $proposedPrice,
        ?string        $responseComment,
    ): void
    {
        DB::transaction(function () use ($recipient, $proposedPrice, $responseComment) {
            [$lockedRecipient, $request] = $this->lockOfferAndRequest($recipient);
            $this->assertOfferActionable($lockedRecipient, $request);

            $lockedRecipient->update([
                'status' => OutsourceOfferStatus::RequestAccepted,
                'responded_at' => now(),
                'proposed_price' => $proposedPrice,
                'response_comment' => $responseComment,
            ]);
        });
    }

    public function declineOffer(
        OutsourceOffer $recipient,
        string         $declineComment,
    ): void
    {
        DB::transaction(function () use ($recipient, $declineComment) {
            [$lockedRecipient, $request] = $this->lockOfferAndRequest($recipient);
            $this->assertOfferActionable($lockedRecipient, $request);

            $lockedRecipient->update([
                'status' => OutsourceOfferStatus::RequestDeclined,
                'responded_at' => now(),
                'decline_comment' => $declineComment,
            ]);

            if ($request->isCascade()) {
                $this->activateNextCascadeOffer($request);
            }
        });
    }

    /**
     * Idempotent — safe to call from both the delayed job and the sweeper.
     */
    public function expireOffer(OutsourceOffer $recipient): void
    {
        DB::transaction(function () use ($recipient) {
            [$lockedRecipient, $request] = $this->lockOfferAndRequest($recipient);

            if ($lockedRecipient->status !== OutsourceOfferStatus::RequestSent) {
                return;
            }

            if ($request->status !== OutsourceRequestStatus::Active) {
                return;
            }

            if (!$this->hasResponseDeadlinePassed($lockedRecipient, $request)) {
                return;
            }

            $lockedRecipient->update(['status' => OutsourceOfferStatus::RequestExpired]);

            if ($request->isCascade()) {
                $this->activateNextCascadeOffer($request);
            }
        });
    }

    /**
     * @param array<string, string> $rejectionComments map of non-selected in-play recipient_id => rejection_comment
     */
    public function selectOffer(
        OutsourceRequest $request,
        OutsourceOffer   $recipient,
        array            $rejectionComments,
    ): void
    {
        DB::transaction(function () use ($request, $recipient, $rejectionComments) {
            $lockedRequest = OutsourceRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== OutsourceRequestStatus::Active) {
                throw new DomainException("Request is not ACTIVE.");
            }

            /** @var OutsourceOffer $lockedRecipient */
            $lockedRecipient = $lockedRequest->offers()
                ->where('id', $recipient->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRecipient->status !== OutsourceOfferStatus::RequestAccepted) {
                throw new DomainException("Recipient is not in REQUEST_ACCEPTED state.");
            }

            /** @var Collection<OutsourceOffer> $rejectedOffers */
            $rejectedRecipients = $lockedRequest->offers()
                ->whereIn('id', array_keys($rejectionComments))
                ->whereIn('status', [
                    OutsourceOfferStatus::RequestPending,
                    OutsourceOfferStatus::RequestSent,
                    OutsourceOfferStatus::RequestAccepted,
                ])
                ->lockForUpdate()
                ->get();

            if ($rejectedRecipients->count() !== count($rejectionComments)) {
                throw new DomainException("One or more rejected recipients are no longer in-play.");
            }

            $lockedRecipient->update(['status' => OutsourceOfferStatus::OfferAccepted]);

            foreach ($rejectedRecipients as $rejectedRecipient) {
                $rejectedRecipient->update([
                    'status' => OutsourceOfferStatus::OfferDeclined,
                    'rejection_comment' => $rejectionComments[$rejectedRecipient->id],
                    'responded_at' => $rejectedRecipient->responded_at ?? now(),
                ]);
            }

            $lockedRequest->update(['status' => OutsourceRequestStatus::Fulfilled]);

            $finalPrice = $lockedRecipient->proposed_price ?? $lockedRequest->price ?? $lockedRecipient->calculated_price;
            $lockedRequest->assignment->update([
                'price' => $finalPrice,
            ]);
        });
    }

    public function cancelRequest(OutsourceRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $lockedRequest = OutsourceRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRequest->offers()
                ->whereIn('status', [
                    OutsourceOfferStatus::RequestPending,
                    OutsourceOfferStatus::RequestSent,
                ])
                ->update(['status' => OutsourceOfferStatus::RequestExpired]);

            $lockedRequest->update(['status' => OutsourceRequestStatus::Cancelled]);
        });
    }

    private function activateNextCascadeOffer(OutsourceRequest $request): void
    {
        if ($request->status !== OutsourceRequestStatus::Active || !$request->isCascade()) {
            return;
        }

        /** @var OutsourceOffer $next */
        $next = $request->offers()
            ->where('status', OutsourceOfferStatus::RequestPending)
            ->orderBy('position')
            ->lockForUpdate()
            ->first();

        if (!$next) {
            return;
        }

        $next->update([
            'status' => OutsourceOfferStatus::RequestSent,
            'notified_at' => now(),
            'expires_at' => now()->addMinutes($request->reaction_time_minutes),
        ]);

        ExpireOutsourceOfferJob::dispatch($next->id)
            ->afterCommit()
            ->delay($next->expires_at);
    }

    /**
     * @return array{OutsourceOffer, OutsourceRequest}
     */
    private function lockOfferAndRequest(OutsourceOffer $recipient): array
    {
        $request = OutsourceRequest::query()
            ->where('id', $recipient->outsource_request_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedOffer = $request->offers()
            ->where('id', $recipient->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedOffer, $request];
    }

    private function assertOfferActionable(
        OutsourceOffer   $recipient,
        OutsourceRequest $request,
    ): void
    {
        if ($recipient->status !== OutsourceOfferStatus::RequestSent) {
            throw new DomainException("Recipient is not in REQUEST_SENT state.");
        }

        if ($request->status !== OutsourceRequestStatus::Active) {
            throw new DomainException("Request is not ACTIVE.");
        }

        if ($this->hasResponseDeadlinePassed($recipient, $request)) {
            throw new DomainException("Recipient response deadline has passed.");
        }
    }

    private function hasResponseDeadlinePassed(
        OutsourceOffer   $recipient,
        OutsourceRequest $request,
    ): bool
    {
        $deadline = $request->isCascade()
            ? $recipient->expires_at
            : $request->deadline_at;

        return $deadline instanceof Carbon && $deadline->lte(now());
    }
}
