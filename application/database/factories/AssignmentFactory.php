<?php

namespace Database\Factories;

use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sub_project_id' => SubProject::factory(),
            'assigned_vendor_id' => fake()->randomElement([null, null, Vendor::factory()]),
            'deadline_at' => fake()->dateTime(),
            'comments' => fake()->text(),
            'assignee_comments' => fake()->realText(),
        ];
    }
}
