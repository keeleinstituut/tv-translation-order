<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\VendorSkillLanguage;
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

class CalendarSearchControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_search_requires_language_id(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search');

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }

    public function test_search_rejects_invalid_language_id(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=not-a-uuid');

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }

    public function test_search_rejects_invalid_duration_minutes(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id . '&duration_minutes=5');

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_minutes']);
    }

    public function test_search_returns_403_for_vendor_role(): void
    {
        // GIVEN — vendor user with no PM/Create privileges
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);
        $language = ClassifierValue::factory()->language()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN
        $response->assertStatus(403);
    }

    public function test_search_succeeds_for_client_role(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN
        $response->assertStatus(200);
    }

    public function test_search_finds_slot_on_current_day(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN — default 60-min slot starting at working hours start (08:00)
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => $today->copy()->setTime(8, 0)->utc()->toIso8601String(),
                    'end_at' => $today->copy()->setTime(9, 0)->utc()->toIso8601String(),
                    'language_id' => $language->id,
                ],
            ]);
    }

    public function test_search_finds_slot_with_custom_duration(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language] = $this->createVendorCoverageWithWorktime($dayName);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN — 30 minute duration
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id . '&duration_minutes=30');

        // THEN — slot is 30 minutes: 08:00–08:30
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => $today->copy()->setTime(8, 0)->utc()->toIso8601String(),
                    'end_at' => $today->copy()->setTime(8, 30)->utc()->toIso8601String(),
                    'language_id' => $language->id,
                ],
            ]);
    }

    public function test_search_respects_datetime_filter(): void
    {
        // GIVEN — vendor with worktime 08:00-17:00, entry blocks 08:00-12:00
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(8, 0),
            'end_at' => $today->copy()->setTime(12, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $searchDatetime = $today->copy()->setTime(10, 0)->utc()->toIso8601String();

        // WHEN — search from 10:00, entry blocks until 12:00
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id . '&datetime=' . urlencode($searchDatetime));

        // THEN — first available slot starts at 12:00 (after the blocking entry)
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => $today->copy()->setTime(12, 0)->utc()->toIso8601String(),
                    'end_at' => $today->copy()->setTime(13, 0)->utc()->toIso8601String(),
                    'language_id' => $language->id,
                ],
            ]);
    }

    public function test_search_advances_to_next_day_when_no_slot_on_requested_day(): void
    {
        // GIVEN — vendor has worktime for today and tomorrow, today is fully blocked
        $today = Carbon::today()->utc();
        $tomorrow = $today->copy()->addDay();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $todayDayName = strtolower($today->format('l'));
        $tomorrowDayName = strtolower($tomorrow->format('l'));

        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime(
            $todayDayName,
            [$tomorrowDayName => ['08:00', '17:00']],
        );

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(8, 0),
            'end_at' => $today->copy()->setTime(17, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN — slot found on tomorrow
        $response->assertStatus(200);
        $startAt = $response->json('data.start_at');
        $this->assertNotNull($startAt);
        $this->assertStringContainsString($tomorrow->toDateString(), $startAt);
    }

    public function test_search_returns_empty_result_when_no_slot_found(): void
    {
        // GIVEN — no vendor coverage set up
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN — empty result, all fields null
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => null,
                    'end_at' => null,
                    'vendor_ids' => null,
                    'language_id' => null,
                ],
            ]);
    }

    public function test_search_client_excludes_vendor_with_emergency_schedule(): void
    {
        // GIVEN — single vendor with emergency schedule covering today
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->subDay(),
            'end_date' => $today->copy()->addDays(31),
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
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN — no slot found because the only vendor has an emergency schedule for the entire search window
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => null,
                    'end_at' => null,
                    'language_id' => null,
                ],
            ]);
    }

    public function test_search_tpm_includes_vendor_with_emergency_schedule(): void
    {
        // GIVEN — single vendor with emergency schedule covering today
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorEmergencySchedule::factory()->create([
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
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN — TPM sees the vendor despite emergency schedule
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => $today->copy()->setTime(8, 0)->utc()->toIso8601String(),
                    'end_at' => $today->copy()->setTime(9, 0)->utc()->toIso8601String(),
                    'language_id' => $language->id,
                ],
            ]);

        $this->assertContains($vendor->id, $response->json('data.vendor_ids'));
    }

    public function test_search_client_response_omits_vendor_ids(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language] = $this->createVendorCoverageWithWorktime($dayName);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id);

        // THEN — slot found but vendor_ids is null for client role
        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.start_at'));
        $this->assertNull($response->json('data.vendor_ids'));
    }

    public function test_search_skips_time_blocked_by_calendar_entries(): void
    {
        // GIVEN — vendor with worktime 08:00-17:00, entry blocks 08:00-10:00
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createVendorCoverageWithWorktime($dayName);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(8, 0),
            'end_at' => $today->copy()->setTime(10, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/search?language_id=' . $language->id . '&duration_minutes=60');

        // THEN — slot starts at 10:00 (after the blocked period)
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'start_at' => $today->copy()->setTime(10, 0)->utc()->toIso8601String(),
                    'end_at' => $today->copy()->setTime(11, 0)->utc()->toIso8601String(),
                    'language_id' => $language->id,
                ],
            ]);

        $this->assertContains($vendor->id, $response->json('data.vendor_ids'));
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $extraDays  additional day worktime overrides, e.g. ['tuesday' => ['08:00', '17:00']]
     * @return array{Institution, ClassifierValue, Vendor}
     */
    private function createVendorCoverageWithWorktime(string $dayName, array $extraDays = []): array
    {
        $today = Carbon::today()->utc();

        $skill = Skill::findByCode(SkillCode::OralInterpretation);

        $worktimeAttrs = [
            'worktime_timezone' => 'UTC',
            "{$dayName}_worktime_start" => '08:00',
            "{$dayName}_worktime_end" => '17:00',
        ];

        foreach ($extraDays as $extraDay => [$start, $end]) {
            $worktimeAttrs["{$extraDay}_worktime_start"] = $start;
            $worktimeAttrs["{$extraDay}_worktime_end"] = $end;
        }

        $institution = Institution::factory()->create($worktimeAttrs);
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
            'date_to' => $today->copy()->addMonth()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        return [$institution, $language, $vendor];
    }
}
