<?php

namespace database\factories\CachedEntities;

use App\Models\CachedEntities\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $shortName = collect()
            ->times(3, fake()->randomLetter(...))
            ->map(Str::upper(...))
            ->implode('');

        return [
            'name' => fake()->company(),
            'short_name' => $shortName,
            'phone' => null,
            'email' => fake()->companyEmail(),
            'logo_url' => fake()->url(),
        ];
    }
}
