<?php

namespace Tests\Feature;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
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
}
