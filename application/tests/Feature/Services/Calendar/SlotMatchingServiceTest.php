<?php

namespace Tests\Feature\Services\Calendar;

use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\VendorSkillLanguage;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorEmergencySchedule;
use App\Services\Calendar\SlotMatchingService;
use Database\Seeders\InstitutionSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SlotMatchingServiceTest extends TestCase
{
    private SlotMatchingService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(InstitutionSettingsSeeder::class);
        $this->service = app(SlotMatchingService::class);
    }

    public function test_pick_best_internal_vendor_excludes_vendor_with_active_emergency_schedule(): void
    {
        // GIVEN
        $dayName = strtolower(Carbon::today()->utc()->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $today = Carbon::today()->utc();
        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->subDay()->format('Y-m-d'),
            'end_date' => $today->copy()->addDay()->format('Y-m-d'),
        ]);

        // WHEN
        $result = $this->service->pickBestInternalVendor(
            $language->id,
            $today->copy()->setHour(10),
            $today->copy()->setHour(11),
            $institution->id,
            collect(),
        );

        // THEN
        $this->assertNull($result);
    }

    public function test_pick_best_internal_vendor_includes_vendor_with_expired_emergency_schedule(): void
    {
        // GIVEN
        $dayName = strtolower(Carbon::today()->utc()->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $today = Carbon::today()->utc();
        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->subDays(10)->format('Y-m-d'),
            'end_date' => $today->copy()->subDay()->format('Y-m-d'),
        ]);

        // WHEN
        $result = $this->service->pickBestInternalVendor(
            $language->id,
            $today->copy()->setHour(10),
            $today->copy()->setHour(11),
            $institution->id,
            collect(),
        );

        // THEN
        $this->assertNotNull($result);
        $this->assertEquals($vendor->id, $result->id);
    }

    public function test_pick_best_internal_vendor_includes_vendor_with_future_emergency_schedule(): void
    {
        // GIVEN
        $dayName = strtolower(Carbon::today()->utc()->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $today = Carbon::today()->utc();
        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->addDay()->format('Y-m-d'),
            'end_date' => $today->copy()->addDays(10)->format('Y-m-d'),
        ]);

        // WHEN
        $result = $this->service->pickBestInternalVendor(
            $language->id,
            $today->copy()->setHour(10),
            $today->copy()->setHour(11),
            $institution->id,
            collect(),
        );

        // THEN
        $this->assertNotNull($result);
        $this->assertEquals($vendor->id, $result->id);
    }

    public function test_pick_best_internal_vendor_includes_vendor_with_soft_deleted_emergency_schedule(): void
    {
        // GIVEN
        $dayName = strtolower(Carbon::today()->utc()->format('l'));
        [$institution, $language, $vendor] = $this->createInternalVendorWithCoverage($dayName);

        $today = Carbon::today()->utc();
        $schedule = VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $today->copy()->subDay()->format('Y-m-d'),
            'end_date' => $today->copy()->addDay()->format('Y-m-d'),
        ]);
        $schedule->delete();

        // WHEN
        $result = $this->service->pickBestInternalVendor(
            $language->id,
            $today->copy()->setHour(10),
            $today->copy()->setHour(11),
            $institution->id,
            collect(),
        );

        // THEN
        $this->assertNotNull($result);
        $this->assertEquals($vendor->id, $result->id);
    }

    public function test_pick_best_internal_vendor_selects_non_emergency_vendor_when_one_has_emergency(): void
    {
        // GIVEN
        $dayName = strtolower(Carbon::today()->utc()->format('l'));
        [$institution, $language, $emergencyVendor] = $this->createInternalVendorWithCoverage($dayName);
        $availableVendor = $this->createAdditionalInternalVendor($institution, $language, $dayName);

        $today = Carbon::today()->utc();
        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $emergencyVendor->id,
            'start_date' => $today->copy()->subDay()->format('Y-m-d'),
            'end_date' => $today->copy()->addDay()->format('Y-m-d'),
        ]);

        // WHEN
        $result = $this->service->pickBestInternalVendor(
            $language->id,
            $today->copy()->setHour(10),
            $today->copy()->setHour(11),
            $institution->id,
            collect(),
        );

        // THEN
        $this->assertNotNull($result);
        $this->assertEquals($availableVendor->id, $result->id);
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

    private function createAdditionalInternalVendor(Institution $institution, ClassifierValue $language, string $dayName): Vendor
    {
        $today = Carbon::today()->utc();
        $skill = Skill::findByCode(SkillCode::OralInterpretation);

        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        VendorSkillLanguage::factory()->create([
            'vendor_id' => $vendor->id,
            'skill_id' => $skill->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        DB::table('vendor_calendar_imports')->insert([
            'id' => Str::orderedUuid()->toString(),
            'vendor_id' => $vendor->id,
            'date_from' => $today->copy()->startOfMonth(),
            'date_to' => $today->copy()->addMonth()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        return $vendor;
    }
}
