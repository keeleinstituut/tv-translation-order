<?php

namespace Database\Factories;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Skill;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Price>
 */
class PriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'skill_id' => fake()->randomElement(Skill::pluck('id')),
            'src_lang_classifier_value_id' => ClassifierValue::factory()->language(),
            'dst_lang_classifier_value_id' => ClassifierValue::factory()->language(),
            'character_fee' => fake()->randomFloat(2, 0, 1000),
            'word_fee' => fake()->randomFloat(2, 0, 1000),
            'page_fee' => fake()->randomFloat(2, 0, 1000),
            'minute_fee' => fake()->randomFloat(2, 0, 1000),
            'hour_fee' => fake()->randomFloat(2, 0, 1000),
            'minimal_fee' => fake()->randomFloat(2, 0, 1000),
            'created_at' => fake()->dateTime(),
            'updated_at' => fake()->dateTime(),
        ];
    }
}
