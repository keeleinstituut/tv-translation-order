<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\SkillCode;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\Price;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Database\Seeders\CalendarSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class VendorCalendarEntryControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_index_tpm_returns_all_institution_vendor_entries(): void
    {
        // GIVEN — vendor with an assignment entry, TPM caller
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $entry = $this->createAssignmentEntry($vendor, $institution, $language, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [['id' => $entry->id, 'vendor_id' => $vendor->id]]]);
    }

    public function test_index_tpm_excludes_vendors_from_other_institutions(): void
    {
        // GIVEN — vendor belongs to a different institution
        $today = Carbon::today()->utc();
        [$institution] = $this->createVendorCoverage();
        [$otherInstitution, $otherLanguage, $otherVendor] = $this->createVendorCoverage();

        $this->createAssignmentEntry($otherVendor, $otherInstitution, $otherLanguage, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — entry from other institution is not visible
        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_index_vendor_returns_only_own_entries(): void
    {
        // GIVEN — two vendors in same institution; caller is vendor A
        $today = Carbon::today()->utc();
        [$institution, $language, $vendorA] = $this->createVendorCoverage();
        [, $languageB, $vendorB] = $this->createVendorCoverage($institution);

        $entryA = $this->createAssignmentEntry($vendorA, $institution, $language, $today);
        $this->createAssignmentEntry($vendorB, $institution, $languageB, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorA->institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — only vendor A's entry is returned
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [['id' => $entryA->id]]]);
    }

    public function test_index_client_returns_only_entries_for_own_projects(): void
    {
        // GIVEN — two projects from two different clients; entries for each
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $clientA = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $clientB = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $entryA = $this->createAssignmentEntry($vendor, $institution, $language, $today, $clientA->id);
        $this->createAssignmentEntry($vendor, $institution, $language, $today, $clientB->id, startHour: 12);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientA->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — only client A's entry is returned
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [['id' => $entryA->id]]]);
    }

    public function test_index_default_returns_assignments_only(): void
    {
        // GIVEN — one assignment entry + one vacation entry
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $assignmentEntry = $this->createAssignmentEntry($vendor, $institution, $language, $today);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(14),
            'end_at' => $today->copy()->setHour(15),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN — no assignments_only param (defaults to true)
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — only the assignment entry is returned
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [['id' => $assignmentEntry->id]]]);
    }

    public function test_index_assignments_only_false_returns_all_entry_types(): void
    {
        // GIVEN — one assignment + one vacation
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $this->createAssignmentEntry($vendor, $institution, $language, $today);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(14),
            'end_at' => $today->copy()->setHour(15),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString() . '&assignments_only=false');

        // THEN — both entries returned
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_index_excludes_entries_outside_date_range(): void
    {
        // GIVEN — entry from yesterday, query for today
        $today = Carbon::today()->utc();
        $yesterday = $today->copy()->subDay();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $this->createAssignmentEntry($vendor, $institution, $language, $yesterday);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_index_requires_date_range_parameters(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries');

        // THEN
        $response->assertStatus(422);
    }

    public function test_destroy_vendor_can_delete_own_entry(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor, $importId] = $this->createVendorCoverage();
        $entry = $this->createExternalCalendarEntry($vendor, $importId, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendor->institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/calendar/vendor-entries/{$entry->id}");

        // THEN
        $response->assertStatus(204);
        $this->assertModelSoftDeleted($entry);
    }

    public function test_destroy_vendor_cannot_delete_another_vendors_entry(): void
    {
        // GIVEN — entry belongs to vendor B, caller is vendor A
        $today = Carbon::today()->utc();
        [$institution, $language, $vendorA] = $this->createVendorCoverage();
        [, $languageB, $vendorB] = $this->createVendorCoverage($institution);

        $entryB = $this->createAssignmentEntry($vendorB, $institution, $languageB, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorA->institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/calendar/vendor-entries/{$entryB->id}");

        // THEN
        $response->assertStatus(403);
        $this->assertNotNull(VendorCalendarEntry::find($entryB->id));
    }

    public function test_destroy_tpm_can_delete_institution_entry(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor, $importId] = $this->createVendorCoverage();
        $entry = $this->createExternalCalendarEntry($vendor, $importId, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/calendar/vendor-entries/{$entry->id}");

        // THEN
        $response->assertStatus(204);
        $this->assertModelSoftDeleted($entry);
    }

    public function test_destroy_tpm_cannot_delete_entry_from_other_institution(): void
    {
        // GIVEN — entry belongs to another institution's vendor
        $today = Carbon::today()->utc();
        [$institution] = $this->createVendorCoverage();
        [$otherInstitution, $otherLanguage, $otherVendor] = $this->createVendorCoverage();
        $entry = $this->createAssignmentEntry($otherVendor, $otherInstitution, $otherLanguage, $today);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/calendar/vendor-entries/{$entry->id}");

        // THEN — policy scope excludes the entry, returns 404
        $response->assertStatus(404);
        $this->assertNotNull(VendorCalendarEntry::find($entry->id));
    }

    public function test_destroy_client_gets_forbidden(): void
    {
        // GIVEN
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();
        $clientUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $entry = $this->createAssignmentEntry($vendor, $institution, $language, $today, $clientUser->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/calendar/vendor-entries/{$entry->id}");

        // THEN
        $response->assertStatus(403);
    }

    /**
     * Create a vendor with coverage in v_vendor_language_coverage, optionally within an existing institution.
     *
     * @return array{Institution, ClassifierValue, Vendor, string}
     */
    private function createVendorCoverage(?Institution $institution = null): array
    {
        $today = Carbon::today()->utc();

        $skill = Skill::findByCode(SkillCode::OralInterpretation);
        $institution ??= Institution::factory()->create([
            'worktime_timezone' => 'UTC',
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

        $importId = Str::orderedUuid()->toString();
        DB::table('vendor_calendar_imports')->insert([
            'id' => $importId,
            'vendor_id' => $vendor->id,
            'date_from' => $today->copy()->startOfMonth(),
            'date_to' => $today->copy()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');

        return [$institution, $language, $vendor, $importId];
    }

    private function createAssignmentEntry(
        Vendor $vendor,
        Institution $institution,
        ClassifierValue $language,
        Carbon $date,
        ?string $clientInstitutionUserId = null,
        int $startHour = 10,
    ): VendorCalendarEntry {
        $sourceLanguage = ClassifierValue::factory()->language()->create();
        $attrs = ['institution_id' => $institution->id];
        if ($clientInstitutionUserId !== null) {
            $attrs['client_institution_user_id'] = $clientInstitutionUserId;
        }
        $project = Project::factory()->create($attrs);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $language->id,
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        return VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => $assignment->id,
            'start_at' => $date->copy()->setHour($startHour),
            'end_at' => $date->copy()->setHour($startHour + 1),
        ]);
    }

    private function createExternalCalendarEntry(
        Vendor $vendor,
        string $importId,
        Carbon $date,
        int $startHour = 10,
    ): VendorCalendarEntry {
        return VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'vendor_calendar_import_id' => $importId,
            'start_at' => $date->copy()->setHour($startHour),
            'end_at' => $date->copy()->setHour($startHour + 1),
        ]);
    }
}
