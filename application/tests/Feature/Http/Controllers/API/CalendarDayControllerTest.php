<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\Price;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\VendorEmergencySchedule;
use Database\Seeders\CalendarSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarDayControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_day_accepts_request_without_parameters(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day');

        // THEN
        $response->assertStatus(200);
    }

    public function test_day_rejects_invalid_date_format(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=not-a-date');

        // THEN
        $response->assertStatus(422);
    }

    public function test_day_vendor_returns_role_vendor_with_calendar_entries(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        $entry = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(10, 0),
            'end_at' => $today->copy()->setTime(11, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.calendar_entries')
            ->assertJson([
                'data' => [
                    'calendar_entries' => [
                        ['id' => $entry->id, 'vendor_id' => $vendor->id],
                    ],
                ],
            ]);
    }

    public function test_day_vendor_returns_empty_entries_when_none_exist(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['calendar_entries' => []]])
            ->assertJsonCount(0, 'data.calendar_entries');
    }

    public function test_day_vendor_excludes_entries_outside_date(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->subDay()->setTime(10, 0),
            'end_at' => $today->copy()->subDay()->setTime(11, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.calendar_entries');
    }

    public function test_day_client_returns_available_slots_tagged_with_language(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language] = $this->createVendorCoverageWithWorktime($dayName);

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
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response->assertStatus(200);

        $slot = $response->json('data.available_slots.0');
        $this->assertNotEmpty($slot);
        $this->assertContains($language->id, $slot['languages']);
    }

    public function test_day_client_vendor_entry_removes_slot_from_available(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        $entry = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(9, 0),
            'end_at' => $today->copy()->setTime(10, 0),
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
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response->assertStatus(200);

        $slots = $response->json('data.available_slots');
        $slotStarts = array_column($slots, 'start_at');
        $this->assertNotContains($entry->start_at->utc()->toIso8601String(), $slotStarts);
    }

    public function test_day_client_partial_hour_free_interval_not_emitted_as_available_slot(): void
    {
        // GIVEN — vendor free only 10:00–10:30 (partial); no full hour available in that window
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(8, 0),
            'end_at' => $today->copy()->setTime(10, 0),
        ]);
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(10, 30),
            'end_at' => $today->copy()->setTime(17, 0),
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
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN — 10:00–10:30 partial must not appear in available_slots
        $response->assertStatus(200);

        $slotStarts = array_column($response->json('data.available_slots') ?? [], 'start_at');
        $partialSlotStart = $today->copy()->setTime(10, 0)->utc()->toIso8601String();
        $this->assertNotContains($partialSlotStart, $slotStarts);
    }

    public function test_day_client_all_vendors_busy_language_appears_in_booked_slots(): void
    {
        // GIVEN — vendor entry covers the entire working window (08:00–17:00 UTC)
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(8, 0),
            'end_at' => $today->copy()->setTime(17, 0),
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
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.available_slots');

        $booked = $response->json('data.booked_slots');
        $this->assertNotEmpty($booked);
        $this->assertContains($language->id, $booked[0]['languages']);
    }

    public function test_day_client_returns_unassigned_projects(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language] = $this->createVendorCoverageWithWorktime($dayName);

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $project = Project::factory()->create([
            'institution_id' => $institution->id,
            'client_institution_user_id' => $clientUser->id,
            'event_start_at' => $today->copy()->setTime(10, 0),
            'event_end_at' => $today->copy()->setTime(12, 0),
            'is_calendar_project' => true,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.unassigned_projects')
            ->assertJson([
                'data' => [
                    'unassigned_projects' => [
                        ['id' => $project->id],
                    ],
                ],
            ]);
    }

    public function test_day_project_manager_returns_available_slots_with_vendor_ids(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response->assertStatus(200);

        $slot = $response->json('data.available_slots.0');
        $this->assertNotEmpty($slot);
        $this->assertContains($vendor->id, $slot['vendor_ids']);
    }

    public function test_day_project_manager_excludes_vendor_from_slot_when_only_partial_hour_free(): void
    {
        // GIVEN — vendor A free 10:00–10:30 only; vendor B free 10:00–11:00 (full)
        // The 10:00–11:00 slot must include vendor B but NOT vendor A
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        $skill = Skill::findByCode(SkillCode::OralInterpretation);
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            "{$dayName}_worktime_start" => '08:00',
            "{$dayName}_worktime_end" => '17:00',
        ]);
        $language = ClassifierValue::factory()->language()->create();

        $vendorA = Vendor::factory()->create([
            'institution_user_id' => InstitutionUser::factory()
                ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
                ->create()->id,
            'company_name' => null,
        ]);
        $vendorB = Vendor::factory()->create([
            'institution_user_id' => InstitutionUser::factory()
                ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
                ->create()->id,
            'company_name' => null,
        ]);

        Price::factory()->create([
            'vendor_id' => $vendorA->id,
            'skill_id' => $skill->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);
        Price::factory()->create([
            'vendor_id' => $vendorB->id,
            'skill_id' => $skill->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        foreach ([$vendorA, $vendorB] as $v) {
            DB::table('vendor_calendar_imports')->insert([
                'id' => Str::orderedUuid()->toString(),
                'vendor_id' => $v->id,
                'date_from' => $today->copy()->startOfMonth(),
                'date_to' => $today->copy()->endOfMonth(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        VendorCalendarEntry::create([
            'vendor_id' => $vendorA->id,
            'start_at' => $today->copy()->setTime(8, 0),
            'end_at' => $today->copy()->setTime(10, 0),
        ]);
        VendorCalendarEntry::create([
            'vendor_id' => $vendorA->id,
            'start_at' => $today->copy()->setTime(10, 30),
            'end_at' => $today->copy()->setTime(17, 0),
        ]);

        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN — 10:00–11:00 slot must not include vendor A (only free 10:00–10:30)
        $response->assertStatus(200);

        $tenOClockSlot = collect($response->json('data.available_slots') ?? [])
            ->first(fn($s) => str_starts_with($s['start_at'], $today->toDateString() . 'T10:'));
        $this->assertNotNull($tenOClockSlot);
        $this->assertNotContains($vendorA->id, $tenOClockSlot['vendor_ids']);
        $this->assertContains($vendorB->id, $tenOClockSlot['vendor_ids']);
    }

    public function test_day_project_manager_returns_vendors_map(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        $institutionUser = $vendor->institutionUser;

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.vendors');

        $v = $response->json('data.vendors.0');
        $this->assertEquals($vendor->id, $v['id']);
        $this->assertEquals($institutionUser->id, $v['institutionUser']['id']);
        $this->assertContains($language->id, $v['languages']);
    }

    public function test_day_project_manager_vendors_map_includes_emergency_schedules(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        $schedule = VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->subDay(),
            'end_date' => $today->copy()->addDay(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN
        $response->assertStatus(200);

        $v = $response->json('data.vendors.0');
        $this->assertNotEmpty($v['emergency_schedules']);
        $this->assertEquals($schedule->id, $v['emergency_schedules'][0]['id']);
        $this->assertEquals($schedule->start_date->toDateString(), $v['emergency_schedules'][0]['start_date']);
        $this->assertEquals($schedule->end_date->toDateString(), $v['emergency_schedules'][0]['end_date']);
    }

    public function test_day_client_excludes_vendor_with_emergency_schedule_from_available_slots(): void
    {
        // GIVEN — single vendor with emergency schedule covering today
        $today = Carbon::today()->utc();
        $dayName = strtolower($today->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->subDay(),
            'end_date' => $today->copy()->addDay(),
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
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN — no available slots because the only vendor has an emergency schedule
        $response
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.available_slots');
    }

    /**
     * Set up a vendor with coverage, worktime, main language, and calendar import
     * configured for the given day, so the day endpoint returns available slots.
     *
     * @return array{Institution, ClassifierValue, Vendor}
     */
    private function createVendorCoverageWithWorktime(string $dayName): array
    {
        $today = Carbon::today()->utc();

        $skill = Skill::findByCode(SkillCode::OralInterpretation);
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            "{$dayName}_worktime_start" => '08:00',
            "{$dayName}_worktime_end" => '17:00',
        ]);
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);
        $language = ClassifierValue::factory()->language()->create();

        Price::factory()->create([
            'vendor_id' => $vendor->id,
            'skill_id' => $skill->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        DB::table('vendor_calendar_imports')->insert([
            'id' => Str::orderedUuid()->toString(),
            'vendor_id' => $vendor->id,
            'date_from' => $today->copy()->startOfMonth(),
            'date_to' => $today->copy()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');

        return [$institution, $language, $vendor];
    }
}
