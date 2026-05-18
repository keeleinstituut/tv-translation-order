<?php

namespace Database\Factories;

use App\Models\CachedEntities\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InstitutionPartner>
 */
class InstitutionPartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'partner_institution_id' => Institution::factory(),
            'discount_percentage_101' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_repetitions' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_100' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_95_99' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_85_94' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_75_84' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_50_74' => fake()->optional()->randomFloat(2, 0, 100),
            'discount_percentage_0_49' => fake()->optional()->randomFloat(2, 0, 100),
        ];
    }
}
