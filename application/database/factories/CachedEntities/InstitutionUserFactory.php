<?php

namespace database\factories\CachedEntities;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * @extends Factory<InstitutionUser>
 */
class InstitutionUserFactory extends Factory
{
    protected $model = InstitutionUser::class;

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
            'archived_at' => null,
            'deactivation_date' => null,
            'user' => $this->generateUserData(),
            'institution' => $institutionData,
            'department' => $this->generateDepartmentData($institutionData['id']),
            'roles' => $this->generateRolesData($institutionData['id']),
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
            'id' => Str::orderedUuid(),
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
            'id' => Str::orderedUuid(),
            'name' => $this->faker->city(),
            'institution_id' => $institutionId,
        ];
    }

    private function generateRolesData(string $institutionId): array
    {
        return [
            [
                'id' => Str::orderedUuid(),
                'name' => fake()->name,
                'institution_id' => $institutionId,
                'privileges' => collect(fake()->randomElements(
                    PrivilegeKey::values(),
                    fake()->numberBetween(1, 16)
                ))->map(fn (string $key) => ['key' => $key])->toArray(),
            ],
        ];
    }

    public function setInstitution($institutionAttributes)
    {
        return $this->state(function ($attributes, $parent) use ($institutionAttributes) {
            return [
                'institution' => [
                    ...$attributes['institution'],
                    ...$institutionAttributes,
                ],
            ];
        });
    }

    /** @throws Throwable */
    public function createWithPrivileges(PrivilegeKey ...$privileges): InstitutionUser
    {
        $institutionUser = $this->create();
        [$newRole] = $this->generateRolesData($institutionUser->institution['id']);
        $newRole['privileges'] = collect($privileges)->map(fn (PrivilegeKey $key) => ['key' => $key->value])->toArray();

        $institutionUser->updateOrFail([
            'roles' => [$newRole],
        ]);

        return $institutionUser;
    }

    /** @throws Throwable */
    public function createWithAllPrivilegesExcept(PrivilegeKey ...$privileges): InstitutionUser
    {
        $institutionUser = $this->create();
        [$newRole] = $this->generateRolesData($institutionUser->institution['id']);
        $newRole['privileges'] = collect(PrivilegeKey::cases())
            ->reject(fn (PrivilegeKey $key) => in_array($key, $privileges))
            ->map(fn (PrivilegeKey $key) => ['key' => $key->value])
            ->toArray();

        $institutionUser->updateOrFail([
            'roles' => [$newRole],
        ]);

        return $institutionUser;
    }
}
