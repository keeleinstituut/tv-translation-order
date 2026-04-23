<?php

namespace Database\Factories;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InstitutionPrice>
 */
class InstitutionPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'skill_id' => fake()->randomElement(Skill::pluck('id')),
            'src_lang_classifier_value_id' => ClassifierValue::factory()->language(),
            'dst_lang_classifier_value_id' => ClassifierValue::factory()->language(),
            'character_fee' => fake()->randomFloat(3, 0, 1000),
            'word_fee' => fake()->randomFloat(3, 0, 1000),
            'page_fee' => fake()->randomFloat(3, 0, 1000),
            'minute_fee' => fake()->randomFloat(3, 0, 1000),
            'hour_fee' => fake()->randomFloat(3, 0, 1000),
            'minimal_fee' => fake()->randomFloat(3, 0, 1000),
        ];
    }
}
