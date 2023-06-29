<?php

namespace Database\Factories;

use App\Models\InstitutionUser;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InstitutionUser>
 */
class InstitutionUserFactory extends Factory
{
    use WithFaker;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->unique()->uuid(),
            'institution_id' => fake()->unique()->uuid(),
            'forename' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'personal_identification_code' => $this->faker->unique()->estonianPIC(),
            'status' => fake()->randomElement(['ACTIVATED']),
            'phone' => fake()->phoneNumber,
            'synced_at' => fake()->dateTime(),
        ];
    }
}
