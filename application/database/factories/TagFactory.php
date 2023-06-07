<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Institution;
use App\Models\Tag;
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
            'type' => $this->faker->randomElement([
                TagType::Order,
                TagType::Vendor,
                TagType::TranslationMemory,
            ]),
            'institution_id' => Institution::factory(),
        ];
    }

    public function vendorSkills(): TagFactory
    {
        return $this->state(fn () => [
            'type' => TagType::VendorSkill,
            'institution_id' => null,
        ]);
    }
}
