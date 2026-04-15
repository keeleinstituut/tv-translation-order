<?php

namespace tests\Feature\Models;

use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Candidate;
use App\Models\SubProject;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class CandidateUniqueIndexTest extends TestCase
{
    public function test_can_recreate_candidate_after_soft_delete(): void
    {
        $assignment = $this->createAssignment();
        $vendor = Vendor::factory()->create();

        $candidate = Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
        ]);
        $candidate->delete();

        $recreated = Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
        ]);

        $this->assertNotNull($recreated->id);
        $this->assertNotEquals($candidate->id, $recreated->id);
        $this->assertModelSoftDeleted($candidate);
    }

    public function test_cannot_create_two_live_candidates_for_same_assignment_and_vendor(): void
    {
        $assignment = $this->createAssignment();
        $vendor = Vendor::factory()->create();

        Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
        ]);

        $this->expectException(QueryException::class);
        Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
        ]);
    }

    private function createAssignment(): Assignment
    {
        $subProject = SubProject::factory()->create([
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        return Assignment::factory()->create(['sub_project_id' => $subProject->id]);
    }
}
