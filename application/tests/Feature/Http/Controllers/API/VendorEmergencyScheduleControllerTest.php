<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use App\Models\VendorEmergencySchedule;
use Tests\AuthHelpers;
use Tests\TestCase;

class VendorEmergencyScheduleControllerTest extends TestCase
{
    public function test_index_returns_emergency_schedules_for_vendor(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-20',
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/emergency-schedules");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'vendor_id', 'start_date', 'end_date', 'created_at']],
            ]);
    }

    public function test_index_requires_edit_vendor_database_privilege(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ViewVendorDatabase->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/emergency-schedules");

        // THEN
        $response->assertStatus(403);
    }

    public function test_store_creates_emergency_schedule(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/vendors/{$vendor->id}/emergency-schedules", [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-05',
            ]);

        // THEN
        $response
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'vendor_id', 'start_date', 'end_date', 'created_at']])
            ->assertJson([
                'data' => [
                    'vendor_id' => $vendor->id,
                    'start_date' => '2026-04-01',
                    'end_date' => '2026-04-05',
                ],
            ]);

        $this->assertDatabaseHas('vendor_emergency_schedules', [
            'vendor_id' => $vendor->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-05',
        ]);
    }

    public function test_store_validates_end_date_after_start_date(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN — end_date before start_date
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/vendors/{$vendor->id}/emergency-schedules", [
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-05',
            ]);

        // THEN
        $response->assertStatus(422)->assertJsonValidationErrors(['end_date']);
    }

    public function test_store_validates_required_fields(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN — empty payload
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/vendors/{$vendor->id}/emergency-schedules", []);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_store_requires_edit_vendor_database_privilege(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/vendors/{$vendor->id}/emergency-schedules", [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-05',
            ]);

        // THEN
        $response->assertStatus(403);
    }

    public function test_store_returns_404_for_vendor_from_other_institution(): void
    {
        // GIVEN — vendor belongs to a different institution
        $institution = Institution::factory()->create();
        $otherInstitution = Institution::factory()->create();
        $otherInstitutionUser = InstitutionUser::factory()->setInstitution(['id' => $otherInstitution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $otherInstitutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/vendors/{$vendor->id}/emergency-schedules", [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-05',
            ]);

        // THEN
        $response->assertStatus(404);
    }

    public function test_destroy_soft_deletes_emergency_schedule(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $schedule = VendorEmergencySchedule::factory()->create(['vendor_id' => $vendor->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/vendors/{$vendor->id}/emergency-schedules/{$schedule->id}");

        // THEN
        $response->assertStatus(204);
        $this->assertModelSoftDeleted($schedule);
    }

    public function test_destroy_requires_edit_vendor_database_privilege(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $schedule = VendorEmergencySchedule::factory()->create(['vendor_id' => $vendor->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/vendors/{$vendor->id}/emergency-schedules/{$schedule->id}");

        // THEN
        $response->assertStatus(403);
        $this->assertNotNull(VendorEmergencySchedule::find($schedule->id));
    }

    public function test_destroy_returns_404_for_schedule_from_other_institution(): void
    {
        // GIVEN — vendor and schedule belong to a different institution
        $institution = Institution::factory()->create();
        $otherInstitution = Institution::factory()->create();
        $otherInstitutionUser = InstitutionUser::factory()->setInstitution(['id' => $otherInstitution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $otherInstitutionUser->id]);
        $schedule = VendorEmergencySchedule::factory()->create(['vendor_id' => $vendor->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::EditVendorDatabase->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/vendors/{$vendor->id}/emergency-schedules/{$schedule->id}");

        // THEN
        $response->assertStatus(404);
    }
}
