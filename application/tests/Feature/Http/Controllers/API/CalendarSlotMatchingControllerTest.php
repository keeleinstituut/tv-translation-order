<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\Price;
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

class CalendarSlotMatchingControllerTest extends TestCase
{
    private const string ENDPOINT = '/api/calendar/slot-matching/vendors';

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_vendors_rejects_client_role(): void
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
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => now()->toIso8601String(),
                'end_at' => now()->addHour()->toIso8601String(),
            ]));

        // THEN
        $response->assertStatus(403);
    }

    public function test_vendors_rejects_vendor_role(): void
    {
        // GIVEN
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
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => now()->toIso8601String(),
                'end_at' => now()->addHour()->toIso8601String(),
            ]));

        // THEN
        $response->assertStatus(403);
    }

    public function test_vendors_validates_required_params(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id', 'start_at', 'end_at']);
    }

    public function test_vendors_validates_end_at_after_start_at(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $now = now();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $now->toIso8601String(),
                'end_at' => $now->copy()->subHour()->toIso8601String(),
            ]));

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_at']);
    }

    public function test_vendors_returns_available_internal_vendor(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(9, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(10, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorIds = collect($response->json('data'))->pluck('id');
        $this->assertContains($vendor->id, $vendorIds->all());

        $vendorData = collect($response->json('data'))->firstWhere('id', $vendor->id);
        $this->assertTrue($vendorData['is_internal']);
        $this->assertNull($vendorData['company_name']);
    }

    public function test_vendors_excludes_internal_vendor_without_calendar_import(): void
    {
        // GIVEN — internal vendor with coverage but NO calendar import
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
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

        // NO calendar import created
        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(9, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(10, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorIds = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($vendor->id, $vendorIds->all());
    }

    public function test_vendors_excludes_internal_vendor_outside_working_hours(): void
    {
        // GIVEN — internal vendor with worktime 08:00-17:00, slot is 18:00-19:00
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(18, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(19, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorIds = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($vendor->id, $vendorIds->all());
    }

    public function test_vendors_excludes_vendor_with_overlapping_entry(): void
    {
        // GIVEN — internal vendor with an overlapping calendar entry
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setTime(9, 0),
            'end_at' => $today->copy()->setTime(11, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(10, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(11, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorIds = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($vendor->id, $vendorIds->all());
    }

    public function test_vendors_returns_external_vendor_without_calendar_import(): void
    {
        // GIVEN — external vendor (has company_name) with coverage but no calendar import
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $skill = Skill::findByCode(SkillCode::OralInterpretation);

        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
        ]);
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $externalVendor = Vendor::factory()->create([
            'institution_user_id' => $institutionUser->id,
            'company_name' => 'External Corp',
        ]);
        $language = ClassifierValue::factory()->language()->create();

        Price::factory()->create([
            'vendor_id' => $externalVendor->id,
            'skill_id' => $skill->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        // NO calendar import — external vendors don't need one
        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(9, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(10, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorIds = collect($response->json('data'))->pluck('id');
        $this->assertContains($externalVendor->id, $vendorIds->all());

        $vendorData = collect($response->json('data'))->firstWhere('id', $externalVendor->id);
        $this->assertFalse($vendorData['is_internal']);
        $this->assertEquals('External Corp', $vendorData['company_name']);
    }

    public function test_vendors_returns_empty_when_no_language_coverage(): void
    {
        // GIVEN — no vendor serves the requested language
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = now()->toIso8601String();
        $endAt = now()->addHour()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    public function test_vendors_includes_overlapping_emergency_schedule(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $emergencySchedule = VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->toDateString(),
            'end_date' => $today->copy()->addDays(5)->toDateString(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(9, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(10, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorData = collect($response->json('data'))->firstWhere('id', $vendor->id);
        $this->assertNotNull($vendorData);
        $this->assertCount(1, $vendorData['emergency_schedules']);
        $this->assertEquals($emergencySchedule->id, $vendorData['emergency_schedules'][0]['id']);
    }

    public function test_vendors_excludes_non_overlapping_emergency_schedule(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        Carbon::setTestNow($today->copy()->setTime(0, 0));

        $dayName = strtolower($today->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->addDays(10)->toDateString(),
            'end_date' => $today->copy()->addDays(20)->toDateString(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value, PrivilegeKey::ViewVendorDatabase->value],
        ]);

        $startAt = $today->copy()->setTime(9, 0)->utc()->toIso8601String();
        $endAt = $today->copy()->setTime(10, 0)->utc()->toIso8601String();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson(self::ENDPOINT . '?' . http_build_query([
                'language_id' => $language->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]));

        // THEN
        $response->assertStatus(200);
        $vendorData = collect($response->json('data'))->firstWhere('id', $vendor->id);
        $this->assertNotNull($vendorData);
        $this->assertCount(0, $vendorData['emergency_schedules']);
    }

    /**
     * @return array{Institution, ClassifierValue, Vendor}
     */
    private function createInternalVendorWithCoverage(string $dayName): array
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
            'date_to' => $today->copy()->addMonth()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');

        return [$institution, $language, $vendor];
    }
}
