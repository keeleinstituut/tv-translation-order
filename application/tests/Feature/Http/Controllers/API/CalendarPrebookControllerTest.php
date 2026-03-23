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
use Database\Seeders\CalendarSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarPrebookControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_prebook_creates_entry_and_returns_expires_at(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
                'tag_ids' => [],
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'calendar_entry' => ['id', 'vendor_id', 'start_at', 'end_at', 'type'],
                    'expires_at',
                ],
            ]);

        $this->assertEquals('prebook', $response->json('data.calendar_entry.type'));
        $this->assertEquals($vendor->id, $response->json('data.calendar_entry.vendor_id'));
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_prebook_rejects_when_user_already_has_active_prebook(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(8),
            'end_at' => $today->copy()->setHour(9),
            'prebook_institution_user_id' => $clientUser->id,
            'prebook_at' => now(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
            ]);

        $response->assertStatus(400);
    }

    public function test_prebook_returns_204_when_no_vendor_available(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        $institution = Institution::factory()->create(['worktime_timezone' => 'UTC']);

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $language = ClassifierValue::factory()->language()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
            ]);

        $response->assertStatus(204);
    }

    public function test_prebook_validation_fails_without_required_fields(): void
    {
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_at', 'end_at', 'language_id']);
    }

    public function test_cancel_prebook_deletes_active_prebook(): void
    {
        $today = Carbon::today()->utc();
        [$institution, , $vendor] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $prebook = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
            'prebook_institution_user_id' => $clientUser->id,
            'prebook_at' => now(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/calendar/prebook');

        $response->assertStatus(204);
        $this->assertModelSoftDeleted($prebook);
    }

    public function test_cancel_prebook_returns_400_when_no_active_prebook(): void
    {
        $institution = Institution::factory()->create();
        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/calendar/prebook');

        $response->assertStatus(400);
    }

    public function test_prebook_with_vendor_id_as_pm_uses_specified_vendor(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
                'vendor_id' => $vendor->id,
            ]);

        $response->assertStatus(200);
        $this->assertEquals($vendor->id, $response->json('data.calendar_entry.vendor_id'));
    }

    public function test_prebook_without_vendor_id_as_pm_returns_422(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        [$institution, $language] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_prebook_with_vendor_id_as_non_pm_returns_422(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        [$institution, $language, $vendor] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
                'vendor_id' => $vendor->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_prebook_with_nonexistent_vendor_id_as_pm_returns_422(): void
    {
        Queue::fake();
        $today = Carbon::today()->utc();
        [$institution, $language] = $this->createInternalVendorCoverage();

        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $clientUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/prebook', [
                'start_at' => $today->copy()->setHour(10)->toIso8601String(),
                'end_at' => $today->copy()->setHour(11)->toIso8601String(),
                'language_id' => $language->id,
                'vendor_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(422);
    }

    /**
     * @return array{Institution, ClassifierValue, Vendor}
     */
    private function createInternalVendorCoverage(?Institution $institution = null): array
    {
        $today = Carbon::today()->utc();
        $skill = Skill::findByCode(SkillCode::OralInterpretation);

        $institution ??= Institution::factory()->create(['worktime_timezone' => 'UTC']);

        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $vendor = Vendor::factory()->create([
            'institution_user_id' => $institutionUser->id,
            'company_name' => null,
        ]);

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
