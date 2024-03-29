<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'vendor_id' => Vendor::factory(),
        ];
    }
}
