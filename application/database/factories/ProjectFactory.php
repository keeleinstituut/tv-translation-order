<?php

namespace Database\Factories;

use App\Enums\ClassifierValueType;
use App\Enums\ProjectStatus;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'comments' => fake()->text(),
            'deadline_at' => fake()->dateTime(),
            'reference_number' => fake()->uuid(),
            'client_institution_user_id' => InstitutionUser::factory(),
            'status' => ProjectStatus::New,
            'type_classifier_value_id' => ClassifierValue::factory()
                ->withType(ClassifierValueType::ProjectType),
            'translation_domain_classifier_value_id' => ClassifierValue::factory()
                ->withType(ClassifierValueType::TranslationDomain),
        ];
    }
}
