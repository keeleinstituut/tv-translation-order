<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\SkillCode;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\VendorSkillLanguage;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\VendorEmergencySchedule;
use Database\Seeders\InstitutionSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarWeekControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->seed(InstitutionSettingsSeeder::class);
    }

    public function test_week_rejects_missing_parameters(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week');

        // THEN
        $response->assertStatus(422);
    }

    public function test_week_rejects_invalid_date_format(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=not-a-date&date_to=also-not-a-date');

        // THEN
        $response->assertStatus(422);
    }

    public function test_week_rejects_end_at_before_start_at(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        $monday = Carbon::today()->utc()->startOfWeek();

        // WHEN — end_at is before start_at
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->subDay()->toDateString());

        // THEN
        $response->assertStatus(422);
    }

    public function test_week_rejects_range_exceeding_21_days(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        $monday = Carbon::today()->utc()->startOfWeek();

        // WHEN — 22-day range exceeds the 21-day limit
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(22)->toDateString());

        // THEN
        $response->assertStatus(422);
    }


    public function test_week_vendor_returns_empty_collection_when_no_assignment_entries(): void
    {
        // GIVEN
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_week_vendor_returns_entries_grouped_by_language_and_slot(): void
    {
        // GIVEN
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        // Entry falls inside the 06:00–12:00 slot on Monday
        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $monday->copy()->setTime(9, 0),
            endAt: $monday->copy()->setTime(10, 0),
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data.slots');

        $slot = $response->json('data.slots.0');
        $this->assertEquals($language->id, $slot['language_id']);
        $this->assertCount(1, $slot['calendar_entries']);
        $this->assertStringContainsString($monday->toDateString(), $slot['start_at']);
    }

    public function test_week_vendor_excludes_entries_outside_date_range(): void
    {
        // GIVEN — entry is in the previous week
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        $previousMonday = $monday->copy()->subWeek();
        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $previousMonday->copy()->setTime(9, 0),
            endAt: $previousMonday->copy()->setTime(10, 0),
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN — request the current week
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — no entries from the previous week
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_week_vendor_excludes_entries_without_assignment_id(): void
    {
        // GIVEN — calendar entry not linked to any assignment
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => null,
            'start_at' => $monday->copy()->setTime(9, 0),
            'end_at' => $monday->copy()->setTime(10, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — assignmentsOnly() scope excludes the entry
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_week_client_returns_empty_when_no_vendor_coverage(): void
    {
        // GIVEN — institution with no main languages
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create();
        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — loadAggregationContext() returns null → empty collection
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_week_client_returns_available_vendors_per_slot(): void
    {
        // GIVEN — vendor with Monday worktime and no bookings
        $monday = Carbon::today()->utc()->startOfWeek();
        Carbon::setTestNow($monday->copy()->setTime(0, 0));
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — at least one slot covers Monday 08:00–17:00 worktime → vendor is available
        $response->assertStatus(200);

        $slots = $response->json('data.slots');
        $this->assertNotEmpty($slots);

        $availableSlot = collect($slots)->firstWhere('available_vendors', '>', 0);
        $this->assertNotNull($availableSlot, 'Expected at least one slot with available_vendors > 0');
        $this->assertEquals(1, $availableSlot['total_vendors']);
        $this->assertEquals($language->id, $availableSlot['language_id']);
    }

    public function test_week_client_booked_vendor_reduces_available_vendors(): void
    {
        // GIVEN — vendor fully booked during Monday working hours (no ≥1h free gap remains)
        $monday = Carbon::today()->utc()->startOfWeek();
        Carbon::setTestNow($monday->copy()->setTime(0, 0));
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        // Block the entire working window on Monday
        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $monday->copy()->setTime(8, 0),
            endAt: $monday->copy()->setTime(17, 0),
        );

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — slots that overlap Monday worktime have available_vendors = 0
        $response->assertStatus(200);

        $slots = $response->json('data.slots');
        $mondaySlots = collect($slots)->filter(
            fn($s) => str_starts_with($s['start_at'], $monday->toDateString())
        );

        $this->assertNotEmpty($mondaySlots);
        foreach ($mondaySlots as $slot) {
            $this->assertEquals(1, $slot['total_vendors']);
            $this->assertEquals(0, $slot['available_vendors']);
        }
    }

    public function test_week_project_manager_returns_empty_when_no_main_languages(): void
    {
        // GIVEN — institution with no main languages configured
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — loadAggregationContext() returns null → empty response
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['available_slots' => [], 'vendors' => []]]);
    }

    public function test_week_project_manager_returns_vendors_map_when_no_entries(): void
    {
        // GIVEN — full vendor setup with worktime but no bookings
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — vendors map populated; available_slots non-empty because vendor is free
        $response->assertStatus(200)->assertJsonCount(1, 'data.vendors');

        $vendorEntry = $response->json('data.vendors.0');
        $this->assertEquals($vendor->id, $vendorEntry['id']);
        $this->assertEquals($vendor->institutionUser->id, $vendorEntry['institutionUser']['id']);
        $this->assertContains($language->id, $vendorEntry['languages']);
    }

    public function test_week_project_manager_returns_available_slots_with_vendor_ids(): void
    {
        // GIVEN — vendor with worktime, no bookings
        $monday = Carbon::today()->utc()->startOfWeek();
        Carbon::setTestNow($monday->copy()->setTime(0, 0));
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — at least one available slot contains the vendor's ID
        $response->assertStatus(200);

        $availableSlots = $response->json('data.available_slots');
        $this->assertNotEmpty($availableSlots);

        $slotWithVendor = collect($availableSlots)->first(
            fn($s) => in_array($vendor->id, $s['vendor_ids'])
        );
        $this->assertNotNull($slotWithVendor, 'Expected at least one slot with the vendor ID');
        $this->assertEquals($language->id, $slotWithVendor['language_id']);
    }

    public function test_week_project_manager_excludes_vendors_without_calendar_import_in_range(): void
    {
        // GIVEN — price + main language exists, but calendar import does not overlap the week
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();

        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        VendorSkillLanguage::factory()->create([
            'vendor_id' => $vendor->id,
            'skill_id' => Skill::findByCode(SkillCode::OralInterpretation)->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        // Calendar import is from a year ago — does not overlap the requested week
        DB::table('vendor_calendar_imports')->insert([
            'id' => Str::orderedUuid()->toString(),
            'vendor_id' => $vendor->id,
            'date_from' => $monday->copy()->subYear()->startOfMonth(),
            'date_to' => $monday->copy()->subYear()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — vendor fails the calendar import filter → empty response
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['available_slots' => [], 'vendors' => []]]);
    }

    public function test_week_project_manager_vendors_map_includes_emergency_schedules(): void
    {
        // GIVEN
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        $schedule = VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $monday,
            'end_date' => $monday->copy()->addDays(2),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data.vendors');

        $vendorEntry = $response->json('data.vendors.0');
        $this->assertNotEmpty($vendorEntry['emergency_schedules']);
        $this->assertEquals($schedule->id, $vendorEntry['emergency_schedules'][0]['id']);
    }

    public function test_week_client_excludes_vendor_with_emergency_schedule_from_availability(): void
    {
        // GIVEN — vendor with Monday worktime but emergency schedule covers Monday
        $monday = Carbon::today()->utc()->startOfWeek();
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '17:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $monday,
            'end_date' => $monday->copy()->addDays(6),
        ]);

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — vendor with emergency schedule should show 0 available vendors on Monday slots
        $response->assertStatus(200);

        $slots = $response->json('data.slots');
        $mondaySlots = collect($slots)->filter(
            fn($s) => str_starts_with($s['start_at'], $monday->toDateString())
        );

        foreach ($mondaySlots as $slot) {
            $this->assertEquals(0, $slot['available_vendors']);
        }
    }

    public function test_week_project_manager_vendor_still_available_when_partially_booked_in_slot(): void
    {
        // GIVEN — vendor works 06:00–18:00 UTC and is booked for only 3h of the 12:00–18:00 slot
        $monday = Carbon::today()->utc()->startOfWeek();
        Carbon::setTestNow($monday->copy()->setTime(0, 0));
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '06:00',
            'monday_worktime_end' => '18:00',
        ]);
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $monday, $monday->copy()->addDays(6));

        // Book vendor for 12:00–15:00 — leaves 15:00–18:00 free (3h ≥ 1h)
        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $monday->copy()->setTime(12, 0),
            endAt: $monday->copy()->setTime(15, 0),
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/week?date_from=' . $monday->toDateString() . '&date_to=' . $monday->copy()->addDays(6)->toDateString());

        // THEN — vendor should still appear in the 12:00–18:00 slot
        $response->assertStatus(200);

        $availableSlots = collect($response->json('data.available_slots'));

        $afternoonSlot = $availableSlots->first(fn($s) => str_contains($s['start_at'], 'T12:00:00')
            && str_starts_with($s['start_at'], $monday->toDateString())
            && $s['language_id'] === $language->id
        );

        $this->assertNotNull($afternoonSlot, 'Vendor should still be available in the 12:00–18:00 slot after partial booking');
        $this->assertContains($vendor->id, $afternoonSlot['vendor_ids'], 'Partially booked vendor should still appear in slot vendor_ids');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create vendor with full coverage setup: institution user, vendor, price, main language, calendar import, and refresh the materialized view.
     */
    private function createVendorSetup(Institution $institution, ClassifierValue $language, Carbon $periodStart, Carbon $periodEnd): Vendor
    {
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        VendorSkillLanguage::factory()->create([
            'vendor_id' => $vendor->id,
            'skill_id' => Skill::findByCode(SkillCode::OralInterpretation)->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::firstOrCreate([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        DB::table('vendor_calendar_imports')->insert([
            'id' => Str::orderedUuid()->toString(),
            'vendor_id' => $vendor->id,
            'date_from' => $periodStart,
            'date_to' => $periodEnd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        return $vendor;
    }

    /**
     * Create a full assignment chain and a linked calendar entry.
     */
    private function createAssignmentEntry(
        Institution     $institution,
        Vendor          $vendor,
        ClassifierValue $language,
        Carbon          $startAt,
        Carbon          $endAt,
    ): void
    {
        $project = Project::factory()->create([
            'institution_id' => $institution->id,
            'client_institution_user_id' => InstitutionUser::factory()
                ->setInstitution(['id' => $institution->id])
                ->create()->id,
        ]);

        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
            'destination_language_classifier_value_id' => $language->id,
        ]);

        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => $assignment->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }
}
