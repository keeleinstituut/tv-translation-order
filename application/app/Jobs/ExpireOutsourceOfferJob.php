<?php

namespace App\Jobs;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Models\OutsourceOffer;
use App\Services\OutsourceRequest\OutsourceRequestStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireOutsourceOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $recipientId)
    {
    }

    public function handle(OutsourceRequestStateMachine $stateMachine): void
    {
        $recipient = OutsourceOffer::find($this->recipientId);

        if (!$recipient) {
            return;
        }

        if ($recipient->status !== OutsourceOfferStatus::RequestSent) {
            return;
        }

        if ($recipient->expires_at === null) {
            return;
        }

        if ($recipient->outsourceRequest->status !== OutsourceRequestStatus::Active) {
            return;
        }

        $stateMachine->expireOffer($recipient);
    }
}
