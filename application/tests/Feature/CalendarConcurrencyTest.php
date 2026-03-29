<?php

namespace Tests\Feature;

use App\Enums\CandidateStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Services\Calendar\CalendarVendorTaskProposalService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarConcurrencyTest extends TestCase
{
    public function test_exclusion_constraint_prevents_overlapping_entries_for_same_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $today = Carbon::today()->utc();

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $this->expectException(QueryException::class);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10)->setMinute(30),
            'end_at' => $today->copy()->setHour(11)->setMinute(30),
        ]);
    }

    public function test_exclusion_constraint_allows_non_overlapping_entries_for_same_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $today = Carbon::today()->utc();

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $entry2 = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(11),
            'end_at' => $today->copy()->setHour(12),
        ]);

        $this->assertDatabaseHas('vendor_calendar_entries', ['id' => $entry2->id]);
    }

    public function test_exclusion_constraint_allows_overlapping_entries_for_different_vendors(): void
    {
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();
        $today = Carbon::today()->utc();

        VendorCalendarEntry::create([
            'vendor_id' => $vendor1->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $entry2 = VendorCalendarEntry::create([
            'vendor_id' => $vendor2->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $this->assertDatabaseHas('vendor_calendar_entries', ['id' => $entry2->id]);
    }

    public function test_exclusion_constraint_allows_overlapping_after_soft_delete(): void
    {
        $vendor = Vendor::factory()->create();
        $today = Carbon::today()->utc();

        $entry1 = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $entry1->delete();

        $entry2 = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $this->assertDatabaseHas('vendor_calendar_entries', ['id' => $entry2->id]);
    }

    public function test_exclusion_constraint_allows_overlapping_vacation_entries(): void
    {
        $vendor = Vendor::factory()->create();
        $today = Carbon::today()->utc();

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
            'institution_user_vacation_id' => fake()->uuid(),
        ]);

        $entry2 = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
            'institution_vacation_id' => fake()->uuid(),
        ]);

        $this->assertDatabaseHas('vendor_calendar_entries', ['id' => $entry2->id]);
    }

    public function test_one_prebook_per_user_index_prevents_duplicate_prebooks(): void
    {
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();
        $today = Carbon::today()->utc();
        $institutionUser = InstitutionUser::factory()->create();

        VendorCalendarEntry::create([
            'vendor_id' => $vendor1->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
            'prebook_institution_user_id' => $institutionUser->id,
            'prebook_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor2->id,
            'start_at' => $today->copy()->setHour(14),
            'end_at' => $today->copy()->setHour(15),
            'prebook_institution_user_id' => $institutionUser->id,
            'prebook_at' => now(),
        ]);
    }

    public function test_decline_cascade_is_atomic_with_transaction(): void
    {
        $today = Carbon::today()->utc();
        [$assignment, $vendor] = $this->createAssignmentWithVendor($today);

        $candidate = Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
            'position' => 0,
            'status' => CandidateStatus::SubmittedToVendor,
            'notified_at' => now(),
        ]);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
            'assignment_id' => $assignment->id,
        ]);

        $service = app(CalendarVendorTaskProposalService::class);
        $service->handleDecline($candidate);

        $candidate->refresh();
        $this->assertEquals(CandidateStatus::Declined, $candidate->status);
    }

    /**
     * @throws \Throwable
     */
    public function test_decline_with_already_declined_candidate_is_idempotent(): void
    {
        $today = Carbon::today()->utc();
        [$assignment, $vendor] = $this->createAssignmentWithVendor($today);

        $candidate = Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
            'position' => 0,
            'status' => CandidateStatus::Declined,
        ]);

        $service = app(CalendarVendorTaskProposalService::class);
        $service->handleDecline($candidate);

        $candidate->refresh();
        $this->assertEquals(CandidateStatus::Declined, $candidate->status);
    }

    /**
     * @return array{Assignment, Vendor}
     */
    private function createAssignmentWithVendor(Carbon $today): array
    {
        $srcLang = ClassifierValue::factory()->language()->create();
        $dstLang = ClassifierValue::factory()->language()->create();

        $project = Project::factory()->create([
            'is_calendar_project' => true,
            'event_start_at' => $today->copy()->setHour(10),
            'event_end_at' => $today->copy()->setHour(11),
        ]);

        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $srcLang->id,
            'destination_language_classifier_value_id' => $dstLang->id,
        ]);

        $vendor = Vendor::factory()->create();

        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        return [$assignment, $vendor];
    }
}
