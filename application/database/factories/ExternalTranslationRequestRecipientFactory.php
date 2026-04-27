<?php

namespace Database\Factories;

use App\Enums\ExternalRequestRecipientStatus;
use App\Models\CachedEntities\Institution;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalTranslationRequestRecipient>
 */
class ExternalTranslationRequestRecipientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_translation_request_id' => ExternalTranslationRequest::factory(),
            'institution_id' => Institution::factory(),
            'position' => 1,
            'status' => ExternalRequestRecipientStatus::Pending,
            'notified_at' => null,
            'responded_at' => null,
            'expires_at' => null,
            'calculated_price' => null,
            'proposed_price' => null,
            'decline_comment' => null,
            'rejection_comment' => null,
            'response_comment' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state([
            'status' => ExternalRequestRecipientStatus::Notified,
            'notified_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }
}
