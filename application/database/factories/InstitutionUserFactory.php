<?php

namespace Database\Factories;

use App\Enums\InstitutionUserStatus;
use App\Models\Institution;
use App\Models\InstitutionUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @extends Factory<InstitutionUser>
 */
class InstitutionUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'user_id' => $this->faker->uuid,
            'department_id' => $this->faker->uuid,
            'forename' => $this->faker->firstName(),
            'surname' => $this->faker->lastName(),
            'personal_identification_code' => $this->faker->estonianPIC(),
            'status' => InstitutionUserStatus::Created,
            'email' => $this->faker->email,
            'phone' => $this->generateRandomEstonianPhoneNumber(),
        ];
    }

    private function generateRandomEstonianPhoneNumber(): string
    {
        return Str::of('+372')
            ->append(fake()->randomElement([' ', '']))
            ->append(fake()->randomElement(['3', '4', '5', '6', '7']))
            ->append(
                Collection::times(
                    fake()->numberBetween(6, 7),
                    fake()->randomDigit(...)
                )->join('')
            )->toString();
    }
}
