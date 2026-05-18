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
use Database\Seeders\CalendarSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarMonthControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_month_rejects_missing_parameters(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month');

        // THEN
        $response->assertStatus(422);
    }

    public function test_month_rejects_invalid_date_format(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=not-a-date&date_to=also-not-a-date');

        // THEN
        $response->assertStatus(422);
    }

    public function test_month_rejects_end_at_before_start_at(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        $today = Carbon::today()->utc();

        // WHEN — end_at is before start_at
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->toDateString() . '&date_to=' . $today->copy()->subDay()->toDateString());

        // THEN
        $response->assertStatus(422);
    }

    public function test_month_rejects_range_exceeding_93_days(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        $today = Carbon::today()->utc();

        // WHEN — 94-day range exceeds the 93-day limit
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->toDateString() . '&date_to=' . $today->copy()->addDays(94)->toDateString());

        // THEN
        $response->assertStatus(422);
    }

    public function test_month_vendor_returns_empty_collection_when_no_assignment_entries(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId'   => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_month_vendor_returns_aggregated_hours_per_language_and_date(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $today->copy()->setTime(10, 0),
            endAt: $today->copy()->setTime(11, 0),
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId'   => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data.slots');

        $slot = $response->json('data.slots.0');
        $this->assertEquals($language->id, $slot['language_id']);
        $this->assertEquals($today->toDateString(), $slot['date']);
        $this->assertEquals(1.0, $slot['vendor_hours']);
    }

    public function test_month_vendor_excludes_other_vendor_entries(): void
    {
        // GIVEN — two vendors, only vendor B has an assignment entry
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();

        $vendorA = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());
        $vendorB = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendorB,
            language: $language,
            startAt: $today->copy()->setTime(10, 0),
            endAt: $today->copy()->setTime(11, 0),
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId'   => $vendorA->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN — request as vendor A
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN — vendor A sees no entries
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_month_vendor_excludes_entries_without_assignment_id(): void
    {
        // GIVEN — vendor has a calendar entry not linked to any assignment
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        VendorCalendarEntry::create([
            'vendor_id'     => $vendor->id,
            'assignment_id' => null,
            'start_at'      => $today->copy()->setTime(10, 0),
            'end_at'        => $today->copy()->setTime(11, 0),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId'   => $vendor->institution_user_id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN — base query requires whereNotNull(assignment_id)
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_month_client_returns_aggregated_hours_for_own_projects(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $today->copy()->setTime(10, 0),
            endAt: $today->copy()->setTime(11, 0),
            clientInstitutionUserId: $clientUser->id,
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId'   => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data.slots');

        $slot = $response->json('data.slots.0');
        $this->assertEquals($language->id, $slot['language_id']);
        $this->assertEquals($today->toDateString(), $slot['date']);
        $this->assertEquals(1.0, $slot['vendor_hours']);
    }

    public function test_month_client_excludes_entries_from_other_clients_projects(): void
    {
        // GIVEN — entry belongs to a different client's project
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $otherClientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $today->copy()->setTime(10, 0),
            endAt: $today->copy()->setTime(11, 0),
            clientInstitutionUserId: $otherClientUser->id,
        );

        $requestingClientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId'   => $requestingClientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN — request as a different client user
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN — the entry belongs to another client's project, not visible
        $response->assertStatus(200)->assertJson(['data' => ['slots' => []]]);
    }

    public function test_month_project_manager_returns_empty_when_no_main_languages(): void
    {
        // GIVEN — institution with no main languages configured
        $institution = Institution::factory()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::ReceiveProject->value],
        ]);

        $today = Carbon::today()->utc();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN — loadCoverageData() returns null → empty response
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['available_slots' => [], 'vendors' => []]]);
    }

    public function test_month_project_manager_returns_vendors_map_when_no_entries(): void
    {
        // GIVEN — full vendor setup but no calendar entries
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN — slots empty, vendors map populated
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['available_slots' => []]])
            ->assertJsonCount(1, 'data.vendors');

        $vendorEntry = $response->json('data.vendors.0');
        $this->assertEquals($vendor->id, $vendorEntry['id']);
        $this->assertEquals($vendor->institutionUser->id, $vendorEntry['institutionUser']['id']);
        $this->assertContains($language->id, $vendorEntry['languages']);
    }

    public function test_month_project_manager_returns_slots_with_per_vendor_hours(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $this->createAssignmentEntry(
            institution: $institution,
            vendor: $vendor,
            language: $language,
            startAt: $today->copy()->setTime(10, 0),
            endAt: $today->copy()->setTime(11, 0),
        );

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data.available_slots');

        $slot = $response->json('data.available_slots.0');
        $this->assertEquals($language->id, $slot['language_id']);
        $this->assertEquals($today->toDateString(), $slot['date']);
        $this->assertArrayHasKey($vendor->id, $slot['vendor_hours']);
        $this->assertEquals(1.0, $slot['vendor_hours'][$vendor->id]);
    }

    public function test_month_project_manager_excludes_vendors_without_calendar_import_in_range(): void
    {
        // GIVEN — main language + price coverage exists, but no calendar import overlapping the range
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();

        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id, 'company_name' => null]);

        VendorSkillLanguage::factory()->create([
            'vendor_id'                    => $vendor->id,
            'skill_id'                     => Skill::findByCode(SkillCode::OralInterpretation)->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        // Calendar import exists but for a different month (no overlap with current month)
        DB::table('vendor_calendar_imports')->insert([
            'id'         => Str::orderedUuid()->toString(),
            'vendor_id'  => $vendor->id,
            'date_from'  => $today->copy()->subYear()->startOfMonth(),
            'date_to'    => $today->copy()->subYear()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN — no vendor passes the calendar import filter → empty response
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['available_slots' => [], 'vendors' => []]]);
    }

    public function test_month_project_manager_vendors_map_includes_emergency_schedules(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        $vendor = $this->createVendorSetup($institution, $language, $today->copy()->startOfMonth(), $today->copy()->endOfMonth());

        $schedule = VendorEmergencySchedule::factory()->create([
            'vendor_id'  => $vendor->id,
            'start_date' => $today,
            'end_date'   => $today->copy()->addDays(5),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges'          => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/month?date_from=' . $today->copy()->startOfMonth()->toDateString() . '&date_to=' . $today->copy()->endOfMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data.vendors');

        $vendorEntry = $response->json('data.vendors.0');
        $this->assertNotEmpty($vendorEntry['emergency_schedules']);
        $this->assertEquals($schedule->id, $vendorEntry['emergency_schedules'][0]['id']);
        $this->assertEquals($schedule->start_date->toDateString(), $vendorEntry['emergency_schedules'][0]['start_date']);
    }

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
            'vendor_id'                    => $vendor->id,
            'skill_id'                     => Skill::findByCode(SkillCode::OralInterpretation)->id,
            'dst_lang_classifier_value_id' => $language->id,
        ]);

        InstitutionMainLanguage::firstOrCreate([
            'institution_id' => $institution->id,
            'language_id'    => $language->id,
        ]);

        DB::table('vendor_calendar_imports')->insert([
            'id'         => Str::orderedUuid()->toString(),
            'vendor_id'  => $vendor->id,
            'date_from'  => $periodStart,
            'date_to'    => $periodEnd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        return $vendor;
    }

    /**
     * Create a full assignment chain and a linked calendar entry.
     */
    private function createAssignmentEntry(
        Institution $institution,
        Vendor $vendor,
        ClassifierValue $language,
        Carbon $startAt,
        Carbon $endAt,
        ?string $clientInstitutionUserId = null,
    ): void
    {
        $project = Project::factory()->create([
            'institution_id' => $institution->id,
            'client_institution_user_id' => $clientInstitutionUserId ?: InstitutionUser::factory()
                ->setInstitution(['id' => $institution->id])
                ->create()->id,
        ]);

        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id'       => ClassifierValue::factory()->language()->create()->id,
            'destination_language_classifier_value_id'  => $language->id,
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
