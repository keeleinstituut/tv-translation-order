<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectTypeConfig>
 */
class ProjectTypeConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type_classifier_value_id' => ClassifierValue::factory(),
            'workflow_process_definition_id' => fake()->uuid(),
            'features' => $this->getFeatures(),
        ];
    }

    public function getFeatures() {
        $possibleFeatures = Feature::values();
        return fake()->randomElements($possibleFeatures, count($possibleFeatures));
    }
}
