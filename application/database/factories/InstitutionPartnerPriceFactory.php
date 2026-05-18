<?php

namespace Database\Factories;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionPartner;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InstitutionPartnerPrice>
 */
class InstitutionPartnerPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_partner_id' => InstitutionPartner::factory(),
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
