<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\CachedEntities\Institution;
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
        return $this->withType(TagType::VendorSkill);
    }

    public function typeVendor(): TagFactory
    {
        return $this->withType(TagType::Vendor);
    }

    public function withType(TagType $type): TagFactory
    {
        if ($type === TagType::VendorSkill) {
            return $this->state(fn () => [
                'type' => $type,
                'institution_id' => null,
            ]);
        }

        return $this->state(fn () => [
            'type' => $type,
        ]);
    }
}
