<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassifierValue>
 */
class ClassifierValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $type = fake()->randomElement(['LANGUAGE', 'SKILL']);

        $value = collect([
            'LANGUAGE' => fake()->languageCode(),
            'SKILL' => fake()->jobTitle(),
        ])->get($type);

        return [
            'type' => $type,
            'value' => $value,
            'name' => $value,
            'meta' => null,
            'synced_at' => fake()->dateTime(),
        ];
    }

    public function language(): Factory
    {
        return $this->setType('LANGUAGE');
    }

    public function skill(): Factory
    {
        return $this->setType('SKILL');
    }

    private function setType($type): Factory
    {
        return $this->state(function (array $attributes) use ($type) {
            return [
                'type' => $type,
            ];
        });
    }
}
