<?php

namespace App\Console\Commands;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Models\ExternalTranslationRequestRecipient;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
use Illuminate\Console\Command;

class SweepExpiredExternalTranslationRequestRecipients extends Command
{
    protected $signature = 'app:sweep-expired-external-translation-request-recipients';

    protected $description = 'Expire external translation request recipients whose reaction deadline has passed';

    public function handle(ExternalTranslationRequestStateMachine $stateMachine): void
    {
        ExternalTranslationRequestRecipient::query()
            ->where('status', ExternalRequestRecipientStatus::Notified)
            ->where('expires_at', '<=', now())
            ->whereHas('externalTranslationRequest', fn($q) => $q->where('status', ExternalRequestStatus::Active))
            ->with('externalTranslationRequest')
            ->each(fn(ExternalTranslationRequestRecipient $recipient) => $stateMachine->expireRecipient($recipient));
    }
}
