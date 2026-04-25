<?php

namespace App\Jobs;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Models\ExternalTranslationRequestRecipient;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireExternalTranslationRequestRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $recipientId)
    {
    }

    public function handle(ExternalTranslationRequestStateMachine $stateMachine): void
    {
        $recipient = ExternalTranslationRequestRecipient::find($this->recipientId);

        if (!$recipient) {
            return;
        }

        if ($recipient->status !== ExternalRequestRecipientStatus::Notified) {
            return;
        }

        if ($recipient->expires_at === null) {
            return;
        }

        if ($recipient->externalTranslationRequest->status !== ExternalRequestStatus::Active) {
            return;
        }

        $stateMachine->expireRecipient($recipient);
    }
}
