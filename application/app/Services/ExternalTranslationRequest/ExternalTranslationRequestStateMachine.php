<?php

namespace App\Services\ExternalTranslationRequest;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Jobs\ExpireExternalTranslationRequestRecipientJob;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

readonly class ExternalTranslationRequestStateMachine
{
    public function acceptRecipient(
        ExternalTranslationRequestRecipient $recipient,
        ?float                              $proposedPrice,
        ?string                             $responseComment,
    ): void
    {
        DB::transaction(function () use ($recipient, $proposedPrice, $responseComment) {
            [$lockedRecipient, $request] = $this->lockRecipientAndRequest($recipient);
            $this->assertRecipientActionable($lockedRecipient, $request);

            $lockedRecipient->update([
                'status' => ExternalRequestRecipientStatus::Accepted,
                'responded_at' => now(),
                'proposed_price' => $proposedPrice,
                'response_comment' => $responseComment,
            ]);
        });
    }

    public function declineRecipient(
        ExternalTranslationRequestRecipient $recipient,
        string                              $declineComment,
    ): void
    {
        DB::transaction(function () use ($recipient, $declineComment) {
            [$lockedRecipient, $request] = $this->lockRecipientAndRequest($recipient);
            $this->assertRecipientActionable($lockedRecipient, $request);

            $lockedRecipient->update([
                'status' => ExternalRequestRecipientStatus::Declined,
                'responded_at' => now(),
                'decline_comment' => $declineComment,
            ]);

            if ($request->isCascade()) {
                $this->activateNextCascadeRecipient($request);
            }
        });
    }

    /**
     * Idempotent — safe to call from both the delayed job and the sweeper.
     */
    public function expireRecipient(ExternalTranslationRequestRecipient $recipient): void
    {
        DB::transaction(function () use ($recipient) {
            [$lockedRecipient, $request] = $this->lockRecipientAndRequest($recipient);

            if ($lockedRecipient->status !== ExternalRequestRecipientStatus::Notified) {
                return;
            }

            if ($request->status !== ExternalRequestStatus::Active) {
                return;
            }

            if (!$this->hasResponseDeadlinePassed($lockedRecipient, $request)) {
                return;
            }

            $lockedRecipient->update(['status' => ExternalRequestRecipientStatus::Expired]);

            if ($request->isCascade()) {
                $this->activateNextCascadeRecipient($request);
            }
        });
    }

    /**
     * @param array<string, string> $rejectionComments map of non-selected in-play recipient_id => rejection_comment
     */
    public function selectRecipient(
        ExternalTranslationRequest          $request,
        ExternalTranslationRequestRecipient $recipient,
        array                               $rejectionComments,
    ): void
    {
        DB::transaction(function () use ($request, $recipient, $rejectionComments) {
            $lockedRequest = ExternalTranslationRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== ExternalRequestStatus::Active) {
                throw new DomainException("Request is not ACTIVE.");
            }

            $lockedRecipient = $lockedRequest->recipients()
                ->where('id', $recipient->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRecipient->status !== ExternalRequestRecipientStatus::Accepted) {
                throw new DomainException("Recipient is not in ACCEPTED state.");
            }

            $rejectedRecipients = $lockedRequest->recipients()
                ->whereIn('id', array_keys($rejectionComments))
                ->whereIn('status', [
                    ExternalRequestRecipientStatus::Pending,
                    ExternalRequestRecipientStatus::Notified,
                    ExternalRequestRecipientStatus::Accepted,
                ])
                ->lockForUpdate()
                ->get();

            if ($rejectedRecipients->count() !== count($rejectionComments)) {
                throw new DomainException("One or more rejected recipients are no longer in-play.");
            }

            $lockedRecipient->update(['status' => ExternalRequestRecipientStatus::Selected]);

            foreach ($rejectedRecipients as $rejectedRecipient) {
                $rejectedRecipient->update([
                    'status' => ExternalRequestRecipientStatus::Rejected,
                    'rejection_comment' => $rejectionComments[$rejectedRecipient->id],
                    'responded_at' => $rejectedRecipient->responded_at ?? now(),
                ]);
            }

            $lockedRequest->update(['status' => ExternalRequestStatus::Fulfilled]);

            $finalPrice = $lockedRecipient->proposed_price ?? $lockedRequest->price ?? $lockedRecipient->calculated_price;
            $lockedRequest->assignment->update([
                'external_institution_id' => $lockedRecipient->institution_id,
                'price' => $finalPrice,
            ]);
        });
    }

    public function cancelRequest(ExternalTranslationRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $lockedRequest = ExternalTranslationRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== ExternalRequestStatus::Active) {
                throw new DomainException("Request is not ACTIVE.");
            }

            $lockedRequest->recipients()
                ->whereIn('status', [
                    ExternalRequestRecipientStatus::Pending,
                    ExternalRequestRecipientStatus::Notified,
                ])
                ->update(['status' => ExternalRequestRecipientStatus::Expired]);

            $lockedRequest->update(['status' => ExternalRequestStatus::Cancelled]);
        });
    }

    private function activateNextCascadeRecipient(ExternalTranslationRequest $request): void
    {
        if ($request->status !== ExternalRequestStatus::Active || !$request->isCascade()) {
            return;
        }

        $next = $request->recipients()
            ->where('status', ExternalRequestRecipientStatus::Pending)
            ->orderBy('position')
            ->lockForUpdate()
            ->first();

        if (!$next) {
            return; // D17: queue exhausted — stays ACTIVE, is_cascade_exhausted flag exposed in resource
        }

        $next->update([
            'status' => ExternalRequestRecipientStatus::Notified,
            'notified_at' => now(),
            'expires_at' => now()->addMinutes($request->reaction_time_minutes),
        ]);

        ExpireExternalTranslationRequestRecipientJob::dispatch($next->id)
            ->afterCommit()
            ->delay($next->expires_at);
    }

    /**
     * @return array{ExternalTranslationRequestRecipient, ExternalTranslationRequest}
     */
    private function lockRecipientAndRequest(ExternalTranslationRequestRecipient $recipient): array
    {
        $request = ExternalTranslationRequest::query()
            ->where('id', $recipient->external_translation_request_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedRecipient = $request->recipients()
            ->where('id', $recipient->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedRecipient, $request];
    }

    private function assertRecipientActionable(
        ExternalTranslationRequestRecipient $recipient,
        ExternalTranslationRequest          $request,
    ): void
    {
        if ($recipient->status !== ExternalRequestRecipientStatus::Notified) {
            throw new DomainException("Recipient is not in NOTIFIED state.");
        }

        if ($request->status !== ExternalRequestStatus::Active) {
            throw new DomainException("Request is not ACTIVE.");
        }

        if ($this->hasResponseDeadlinePassed($recipient, $request)) {
            throw new DomainException("Recipient response deadline has passed.");
        }
    }

    private function hasResponseDeadlinePassed(
        ExternalTranslationRequestRecipient $recipient,
        ExternalTranslationRequest          $request,
    ): bool
    {
        $deadline = $request->isCascade()
            ? $recipient->expires_at
            : $request->deadline_at;

        return $deadline instanceof Carbon && $deadline->lte(now());
    }
}
