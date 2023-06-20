<?php

namespace Database\Factories;

use App\Enums\InstitutionUserStatus;
use App\Models\Cached\Institution;
use App\Models\Cached\InstitutionUser;
use Carbon\Carbon;
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
        $institutionData = $this->generateInstitutionData();
        return [
            'email' => $this->faker->email,
            'phone' => $this->generateRandomEstonianPhoneNumber(),
            'user' => $this->generateUserData(),
            'institution' => $institutionData,
            'department' => $this->generateDepartmentData($institutionData['id']),
            'synced_at' => Carbon::now()
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

    private function generateUserData(): array
    {
        return [
            'forename' => $this->faker->firstName(),
            'surname' => $this->faker->lastName(),
            'personal_identification_code' => $this->faker->estonianPIC(),
        ];
    }

    private function generateInstitutionData(): array
    {
        return Institution::factory()->create()->getAttributes();
    }

    private function generateDepartmentData(string $institutionId): array
    {
        return [
            'name' => $this->faker->city(),
            'institution_id' => $institutionId,
        ];
    }
}
