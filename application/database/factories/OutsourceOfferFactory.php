<?php

namespace Database\Factories;

use App\Enums\OutsourceOfferStatus;
use App\Models\CachedEntities\Institution;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutsourceOffer>
 */
class OutsourceOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'outsource_request_id' => OutsourceRequest::factory(),
            'institution_id' => Institution::factory(),
            'position' => 1,
            'status' => OutsourceOfferStatus::RequestPending,
            'notified_at' => null,
            'responded_at' => null,
            'expires_at' => null,
            'price' => null,
            'decline_comment' => null,
            'rejection_comment' => null,
            'response_comment' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state([
            'status' => OutsourceOfferStatus::RequestSent,
            'notified_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => OutsourceOfferStatus::OfferAccepted,
            'notified_at' => now(),
            'responded_at' => now(),
        ]);
    }
}
