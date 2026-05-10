<?php

namespace Database\Factories;

use App\Enums\ExternalRequestMode;
use App\Enums\OutsourceRequestStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\OutsourceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutsourceRequest>
 */
class OutsourceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'institution_user_id' => InstitutionUser::factory(),
            'mode' => ExternalRequestMode::Parallel,
            'reaction_time_minutes' => 4320,
            'special_instructions' => null,
            'price' => null,
            'include_price' => true,
            'include_source_files' => true,
            'status' => OutsourceRequestStatus::Active,
        ];
    }

    public function cascade(): static
    {
        return $this->state([
            'mode' => ExternalRequestMode::Cascade,
            'reaction_time_minutes' => 60,
        ]);
    }
}
