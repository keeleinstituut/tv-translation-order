<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\VendorSkillLanguage;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\VendorEmergencySchedule;
use Database\Seeders\InstitutionSettingsSeeder;
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

        $this->seed(InstitutionSettingsSeeder::class);
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

    public function test_day_client_partial_hour_free_interval_emitted_as_available_slot(): void
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

        // THEN — 10:00–10:30 partial slot should appear in available_slots
        $response->assertStatus(200);

        $partialSlot = collect($response->json('data.available_slots') ?? [])
            ->first(fn($s) => $s['start_at'] === $today->copy()->setTime(10, 0)->utc()->toIso8601String()
                && $s['end_at'] === $today->copy()->setTime(10, 30)->utc()->toIso8601String());
        $this->assertNotNull($partialSlot, 'Partial 10:00–10:30 slot should be present in available_slots');
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
            'privileges' => [PrivilegeKey::ReceiveProject->value],
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

    public function test_day_project_manager_partial_hour_vendor_gets_own_slot(): void
    {
        // GIVEN — vendor A free 10:00–10:30 only; vendor B free 10:00–11:00 (full)
        // Vendor B gets the full-hour 10:00–11:00 slot; vendor A gets a partial 10:00–10:30 slot
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

        VendorSkillLanguage::factory()->create([
            'vendor_id' => $vendorA->id,
            'skill_id' => $skill->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);
        VendorSkillLanguage::factory()->create([
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


        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/day?date=' . $today->toDateString());

        // THEN — full-hour 10:00–11:00 slot has only vendor B;
        //         partial 10:00–10:30 slot has only vendor A
        $response->assertStatus(200);

        $tenStart = $today->copy()->setTime(10, 0)->utc()->toIso8601String();
        $tenThirty = $today->copy()->setTime(10, 30)->utc()->toIso8601String();
        $eleven = $today->copy()->setTime(11, 0)->utc()->toIso8601String();

        $slots = collect($response->json('data.available_slots') ?? []);

        $fullHourSlot = $slots->first(fn($s) => $s['start_at'] === $tenStart && $s['end_at'] === $eleven);
        $this->assertNotNull($fullHourSlot, 'Full-hour 10:00–11:00 slot should exist');
        $this->assertContains($vendorB->id, $fullHourSlot['vendor_ids']);
        $this->assertNotContains($vendorA->id, $fullHourSlot['vendor_ids']);

        $partialSlot = $slots->first(fn($s) => $s['start_at'] === $tenStart && $s['end_at'] === $tenThirty);
        $this->assertNotNull($partialSlot, 'Partial 10:00–10:30 slot should exist');
        $this->assertContains($vendorA->id, $partialSlot['vendor_ids']);
        $this->assertNotContains($vendorB->id, $partialSlot['vendor_ids']);
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
            'privileges' => [PrivilegeKey::ReceiveProject->value],
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
            'privileges' => [PrivilegeKey::ReceiveProject->value],
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

        VendorSkillLanguage::factory()->create([
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


        return [$institution, $language, $vendor];
    }
}
