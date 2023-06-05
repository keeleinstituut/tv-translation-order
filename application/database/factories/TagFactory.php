<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Institution;
use App\Models\Tag;
use Arr;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'type' => $this->faker->randomElement(TagType::cases()),
            'institution_id' => Institution::factory()
        ];
    }

    public function notVendorSkills(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => $this->faker->randomElement([
                    TagType::Order,
                    TagType::Vendor,
                    TagType::TranslationMemory
                ]),
            ];
        });
    }
}
