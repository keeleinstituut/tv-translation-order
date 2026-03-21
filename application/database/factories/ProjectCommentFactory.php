<?php

namespace Database\Factories;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectComment>
 */
class ProjectCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'institution_user_id' => InstitutionUser::factory(),
            'comment' => fake()->sentence(),
        ];
    }
}
