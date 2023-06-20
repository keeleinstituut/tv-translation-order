<?php

namespace database\factories\CachedEntities;

use App\Models\CachedEntities\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'short_name' => null,
            'phone' => null,
            'email' => fake()->companyEmail(),
            'logo_url' => fake()->url(),
        ];
    }
}
