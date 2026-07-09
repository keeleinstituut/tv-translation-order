<?php

namespace App\Services\OutsourceRequest;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestPriceMode;
use App\Enums\OutsourceRequestStatus;
use App\Jobs\ExpireOutsourceOfferJob;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

readonly class OutsourceRequestStateMachine
{
    public function acceptOffer(
        OutsourceOffer $offer,
        ?float         $price,
        ?string        $responseComment,
    ): void
    {
        DB::transaction(function () use ($offer, $price, $responseComment) {
            [$lockedOffer, $request] = $this->lockOfferAndRequest($offer);
            $this->assertOfferActionable($lockedOffer, $request);

            match ($request->price_mode) {
                OutsourceRequestPriceMode::FixedPrice => $this->assertNullPrice($price, 'Cannot set price when price mode is FIXED_PRICE.'),
                OutsourceRequestPriceMode::PriceListBased => $this->assertPriceListBasedAccept($lockedOffer, $price),
                OutsourceRequestPriceMode::AskForPrice => $this->assertNonNullPrice($price, 'Price is required when price mode is ASK_FOR_PRICE.'),
            };

            $data = [
                'status' => OutsourceOfferStatus::RequestAccepted,
                'responded_at' => now(),
                'response_comment' => $responseComment,
            ];
            if ($price !== null) {
                $data['price'] = $price;
            }
            $lockedOffer->update($data);
        });
    }

    public function declineOffer(
        OutsourceOffer $offer,
        string         $declineComment,
    ): void
    {
        DB::transaction(function () use ($offer, $declineComment) {
            [$lockedOffer, $request] = $this->lockOfferAndRequest($offer);
            $this->assertOfferActionable($lockedOffer, $request);

            $lockedOffer->update([
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
    public function expireOffer(OutsourceOffer $offer): void
    {
        DB::transaction(function () use ($offer) {
            [$lockedOffer, $request] = $this->lockOfferAndRequest($offer);

            if ($lockedOffer->status !== OutsourceOfferStatus::RequestSent) {
                return;
            }

            if ($request->status !== OutsourceRequestStatus::Active) {
                return;
            }

            if (!$this->hasResponseDeadlinePassed($lockedOffer)) {
                return;
            }

            $lockedOffer->update(['status' => OutsourceOfferStatus::RequestExpired]);

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
        OutsourceOffer   $offer,
        array            $rejectionComments,
    ): void
    {
        DB::transaction(function () use ($request, $offer, $rejectionComments) {
            $lockedRequest = OutsourceRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== OutsourceRequestStatus::Active) {
                throw new DomainException("Request is not ACTIVE.");
            }

            /** @var OutsourceOffer $lockedOffer */
            $lockedOffer = $lockedRequest->offers()
                ->where('id', $offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOffer->status !== OutsourceOfferStatus::RequestAccepted) {
                throw new DomainException("Recipient is not in REQUEST_ACCEPTED state.");
            }

            /** @var Collection<OutsourceOffer> $rejectedOffers */
            $rejectedOffers = $lockedRequest->offers()
                ->whereIn('id', array_keys($rejectionComments))
                ->whereIn('status', [
                    OutsourceOfferStatus::RequestPending,
                    OutsourceOfferStatus::RequestSent,
                    OutsourceOfferStatus::RequestAccepted,
                ])
                ->lockForUpdate()
                ->get();

            if ($rejectedOffers->count() !== count($rejectionComments)) {
                throw new DomainException("One or more rejected recipients are no longer in-play.");
            }

            $finalPrice = $lockedOffer->price;

            $lockedOffer->update(['status' => OutsourceOfferStatus::OfferAccepted]);

            foreach ($rejectedOffers as $rejectedOffer) {
                $rejectedOffer->update([
                    'status' => OutsourceOfferStatus::OfferDeclined,
                    'rejection_comment' => $rejectionComments[$rejectedOffer->id],
                    'responded_at' => $rejectedOffer->responded_at ?? now(),
                ]);
            }

            $lockedRequest->update([
                'status' => OutsourceRequestStatus::Fulfilled,
                'price' => $finalPrice,
            ]);

            $lockedRequest->assignment->update([
                'price' => $finalPrice,
            ]);
        });
    }

    public function cancelRequest(OutsourceRequest $request, string $cancellationReason): void
    {
        DB::transaction(function () use ($request, $cancellationReason) {
            $lockedRequest = OutsourceRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRequest->update([
                'status' => OutsourceRequestStatus::Cancelled,
                'cancellation_reason' => $cancellationReason,
            ]);
        });
    }

    private function activateNextCascadeOffer(OutsourceRequest $request): void
    {
        if ($request->status !== OutsourceRequestStatus::Active || !$request->isCascade()) {
            return;
        }

        /** @var OutsourceOffer|null $next */
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
            'expires_at' => $request->reaction_time_minutes !== null
                ? now()->addMinutes($request->reaction_time_minutes)
                : null,
        ]);

        ExpireOutsourceOfferJob::dispatch($next->id)
            ->afterCommit()
            ->delay($next->expires_at);
    }

    /**
     * @return array{OutsourceOffer, OutsourceRequest}
     */
    private function lockOfferAndRequest(OutsourceOffer $offer): array
    {
        $request = OutsourceRequest::query()
            ->where('id', $offer->outsource_request_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedOffer = $request->offers()
            ->where('id', $offer->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedOffer, $request];
    }

    private function assertOfferActionable(
        OutsourceOffer   $offer,
        OutsourceRequest $request,
    ): void
    {
        if ($offer->status !== OutsourceOfferStatus::RequestSent) {
            throw new DomainException("Recipient is not in REQUEST_SENT state.");
        }

        if ($request->status !== OutsourceRequestStatus::Active) {
            throw new DomainException("Request is not ACTIVE.");
        }

        if ($this->hasResponseDeadlinePassed($offer)) {
            throw new DomainException("Recipient response deadline has passed.");
        }
    }

    private function assertNullPrice(?float $price, string $message): void
    {
        if ($price !== null) {
            throw new DomainException($message);
        }
    }

    private function assertNonNullPrice(?float $price, string $message): void
    {
        if ($price === null) {
            throw new DomainException($message);
        }
    }

    private function assertPriceListBasedAccept(OutsourceOffer $lockedOffer, ?float $price): void
    {
        if ($lockedOffer->price === null) {
            if ($price === null) {
                throw new DomainException('Price is required when the pricelist-based price is unavailable.');
            }
        } else {
            if ($price !== null) {
                throw new DomainException('Cannot override price when a pricelist-based price is already set.');
            }
        }
    }

    private function hasResponseDeadlinePassed(OutsourceOffer $offer): bool
    {
        return $offer->expires_at instanceof Carbon
            && $offer->expires_at->lte(now());
    }
}
