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
use Database\Seeders\CalendarSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class VendorCalendarEntryStoreTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_store_tpm_creates_absence_entry(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'absence')
            ->assertJsonPath('data.vendor_id', $vendor->id);

        $entry = VendorCalendarEntry::where('vendor_id', $vendor->id)
            ->absencesOnly()
            ->first();
        $this->assertNotNull($entry);
        $this->assertEquals($tpmUser->id, $entry->absence_creator_institution_user_id);
    }

    public function test_store_receive_project_creates_absence_entry(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'absence');
    }

    public function test_store_with_comment_stores_metadata(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
                'comment' => 'Vendor is on sick leave',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.metadata.comment', 'Vendor is on sick leave');

        $entry = VendorCalendarEntry::where('vendor_id', $vendor->id)->absencesOnly()->first();
        $this->assertEquals(['comment' => 'Vendor is on sick leave'], $entry->metadata);
    }

    public function test_store_absence_overlapping_existing_assignment_succeeds(): void
    {
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $this->createAssignmentEntry($vendor, $institution, $language, $today, startHour: 10);

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
            ]);

        $response->assertStatus(201);
    }

    public function test_store_forbidden_for_vendor_in_other_institution(): void
    {
        $today = Carbon::today()->utc();
        [$institution] = $this->createVendorCoverage();
        [, , $otherVendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $otherVendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
            ]);

        $response->assertStatus(404);
    }

    public function test_store_forbidden_for_client(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
            ]);

        $response->assertStatus(403);
    }

    public function test_store_forbidden_for_vendor_user(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendor->institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(9)->toIso8601String(),
                'end_at' => $today->copy()->setHour(17)->toIso8601String(),
            ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $institution = Institution::factory()->create();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vendor_id', 'start_at', 'end_at']);
    }

    public function test_store_validates_end_after_start(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/vendor-entries', [
                'vendor_id' => $vendor->id,
                'start_at' => $today->copy()->setHour(17)->toIso8601String(),
                'end_at' => $today->copy()->setHour(9)->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_at']);
    }

    public function test_destroy_absence_entry_succeeds_for_tpm(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $entry = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(9),
            'end_at' => $today->copy()->setHour(17),
            'absence_creator_institution_user_id' => $tpmUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/calendar/vendor-entries/{$entry->id}");

        $response->assertStatus(204);
        $this->assertModelSoftDeleted($entry);
    }

    public function test_index_type_filter_returns_only_absences(): void
    {
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $tpmUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $this->createAssignmentEntry($vendor, $institution, $language, $today, startHour: 10);

        $absenceEntry = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(14),
            'end_at' => $today->copy()->setHour(15),
            'absence_creator_institution_user_id' => $tpmUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $tpmUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ReceiveProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/vendor-entries?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString() . '&type=absence');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $absenceEntry->id)
            ->assertJsonPath('data.0.type', 'absence');
    }

    public function test_non_absence_entries_still_enforce_overlap_constraint(): void
    {
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createVendorCoverage();

        $this->createAssignmentEntry($vendor, $institution, $language, $today, startHour: 10);

        $this->expectException(\Illuminate\Database\QueryException::class);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
            'vendor_calendar_import_id' => Str::orderedUuid()->toString(),
        ]);
    }

    /**
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

        VendorSkillLanguage::factory()->create([
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
}
