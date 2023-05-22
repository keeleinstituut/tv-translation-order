<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CachedEntities\InstitutionUser;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_user_id' => InstitutionUser::factory(),
            'company_name' => fake()->company(),
            'discount_percentage_101' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_repetitions' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_100' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_95_99' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_85_94' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_75_84' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_50_74' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_0_49' => fake()->randomFloat(2, 0, 100),
        ];
    }
}
