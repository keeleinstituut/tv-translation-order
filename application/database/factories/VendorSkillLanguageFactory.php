<?php

namespace Database\Factories;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Price;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorSkillLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorSkillLanguage>
 */
class VendorSkillLanguageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'skill_id' => fake()->randomElement(Skill::pluck('id')),
            'src_lang_classifier_value_id' => ClassifierValue::factory()->language(),
            'dst_lang_classifier_value_id' => ClassifierValue::factory()->language(),
            'created_at' => fake()->dateTime(),
            'updated_at' => fake()->dateTime(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (VendorSkillLanguage $vendorSkillLanguage): void {
            Price::query()->firstOrCreate(
                [
                    'vendor_id' => $vendorSkillLanguage->vendor_id,
                    'skill_id' => $vendorSkillLanguage->skill_id,
                    'src_lang_classifier_value_id' => $vendorSkillLanguage->src_lang_classifier_value_id,
                    'dst_lang_classifier_value_id' => $vendorSkillLanguage->dst_lang_classifier_value_id,
                ],
                Price::factory()->make()->only([
                    'character_fee',
                    'word_fee',
                    'page_fee',
                    'minute_fee',
                    'hour_fee',
                    'minimal_fee',
                ]),
            );
        });
    }
}
