<?php

namespace App\Console\Commands;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Models\OutsourceOffer;
use App\Services\OutsourceRequest\OutsourceRequestStateMachine;
use Illuminate\Console\Command;

class SweepExpiredOutsourceOffers extends Command
{
    protected $signature = 'app:sweep-expired-outsource-offers';

    protected $description = 'Expire outsource offers whose reaction deadline has passed';

    public function handle(OutsourceRequestStateMachine $stateMachine): void
    {
        OutsourceOffer::query()
            ->where('status', OutsourceOfferStatus::RequestSent)
            ->where('expires_at', '<=', now())
            ->whereHas('outsourceRequest', fn($q) => $q->where('status', OutsourceRequestStatus::Active))
            ->with('outsourceRequest')
            ->each(fn(OutsourceOffer $offer) => $stateMachine->expireOffer($offer));
    }
}
