<?php

namespace Database\Factories;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ExternalTranslationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalTranslationRequest>
 */
class ExternalTranslationRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'created_by_institution_user_id' => InstitutionUser::factory(),
            'mode' => ExternalRequestMode::Parallel,
            'reaction_time_minutes' => null,
            'deadline_at' => now()->addDays(3),
            'special_instructions' => null,
            'price' => null,
            'include_price' => true,
            'include_source_files' => true,
            'status' => ExternalRequestStatus::Active,
        ];
    }

    public function cascade(): static
    {
        return $this->state([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
            'deadline_at' => null,
        ]);
    }
}
