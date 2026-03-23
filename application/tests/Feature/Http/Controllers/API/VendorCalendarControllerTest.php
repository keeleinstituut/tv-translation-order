<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\VendorEmergencySchedule;
use Illuminate\Support\Carbon;
use Tests\AuthHelpers;
use Tests\TestCase;

class VendorCalendarControllerTest extends TestCase
{
    public function test_returns_booked_and_total_hours_for_normal_day(): void
    {
        // GIVEN — vendor with 08:00–16:00 UTC worktime and a 2-hour assignment
        $date = Carbon::parse('2026-04-06'); // Monday
        [$institution, $vendor, $accessToken] = $this->createVendorWithWorktime($date);

        $this->createAssignmentEntry($vendor, $institution, $date->copy()->setHour(10), $date->copy()->setHour(12));

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from={$date->toDateString()}&date_to={$date->toDateString()}");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [[
                'date' => $date->toDateString(),
                'booked_hours' => 2.0,
                'total_hours' => 8.0,
                'is_emergency' => false,
                'is_fully_booked' => false,
            ]]]);
    }

    public function test_emergency_day_returns_null_hours(): void
    {
        // GIVEN — vendor with emergency schedule covering the date
        $date = Carbon::parse('2026-04-06');
        [$institution, $vendor, $accessToken] = $this->createVendorWithWorktime($date);

        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from={$date->toDateString()}&date_to={$date->toDateString()}");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [[
                'date' => $date->toDateString(),
                'booked_hours' => null,
                'total_hours' => null,
                'is_emergency' => true,
                'is_fully_booked' => false,
            ]]]);
    }

    public function test_fully_booked_day_returns_null_hours(): void
    {
        // GIVEN — non-assignment entry covering the entire 08:00–16:00 work window
        $date = Carbon::parse('2026-04-06');
        [$institution, $vendor, $accessToken] = $this->createVendorWithWorktime($date);

        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $date->copy()->setHour(8)->utc(),
            'end_at' => $date->copy()->setHour(16)->utc(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from={$date->toDateString()}&date_to={$date->toDateString()}");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [[
                'date' => $date->toDateString(),
                'booked_hours' => null,
                'total_hours' => null,
                'is_emergency' => false,
                'is_fully_booked' => true,
            ]]]);
    }

    public function test_multiple_days_with_mixed_statuses(): void
    {
        // GIVEN — 3-day range: normal day, emergency day, fully-booked day
        $monday = Carbon::parse('2026-04-06');
        $tuesday = Carbon::parse('2026-04-07');
        $wednesday = Carbon::parse('2026-04-08');

        [$institution, $vendor, $accessToken] = $this->createVendorWithWorktime($monday);

        // Monday: 1h assignment
        $this->createAssignmentEntry($vendor, $institution, $monday->copy()->setHour(9), $monday->copy()->setHour(10));

        // Tuesday: emergency
        VendorEmergencySchedule::factory()->create([
            'vendor_id' => $vendor->id,
            'start_date' => $tuesday->toDateString(),
            'end_date' => $tuesday->toDateString(),
        ]);

        // Wednesday: fully booked by vacation entry
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $wednesday->copy()->setHour(8)->utc(),
            'end_at' => $wednesday->copy()->setHour(16)->utc(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from={$monday->toDateString()}&date_to={$wednesday->toDateString()}");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJson(['data' => [
                [
                    'date' => $monday->toDateString(),
                    'booked_hours' => 1.0,
                    'total_hours' => 8.0,
                    'is_emergency' => false,
                    'is_fully_booked' => false,
                ],
                [
                    'date' => $tuesday->toDateString(),
                    'booked_hours' => null,
                    'total_hours' => null,
                    'is_emergency' => true,
                    'is_fully_booked' => false,
                ],
                [
                    'date' => $wednesday->toDateString(),
                    'booked_hours' => null,
                    'total_hours' => null,
                    'is_emergency' => false,
                    'is_fully_booked' => true,
                ],
            ]]);
    }

    public function test_validation_requires_date_range(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar");

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from', 'date_to']);
    }

    public function test_unauthorized_user_gets_forbidden(): void
    {
        // GIVEN — user has no relevant privilege and is not a vendor
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from=2026-04-06&date_to=2026-04-06");

        // THEN
        $response->assertStatus(403);
    }

    public function test_vendor_from_other_institution_returns_404(): void
    {
        // GIVEN — vendor belongs to a different institution
        $institution = Institution::factory()->create();
        $otherInstitution = Institution::factory()->create();
        $otherUser = InstitutionUser::factory()->setInstitution(['id' => $otherInstitution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $otherUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from=2026-04-06&date_to=2026-04-06");

        // THEN
        $response->assertStatus(404);
    }

    public function test_day_with_no_worktime_returns_null_total_hours(): void
    {
        // GIVEN — institution has no worktime configured
        $date = Carbon::parse('2026-04-06');
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/vendors/{$vendor->id}/calendar?date_from={$date->toDateString()}&date_to={$date->toDateString()}");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson(['data' => [[
                'date' => $date->toDateString(),
                'booked_hours' => 0,
                'total_hours' => null,
                'is_emergency' => false,
                'is_fully_booked' => false,
            ]]]);
    }

    /**
     * @return array{Institution, Vendor, string}
     */
    private function createVendorWithWorktime(Carbon $date): array
    {
        $institution = Institution::factory()->create([
            'worktime_timezone' => 'UTC',
            'monday_worktime_start' => '08:00',
            'monday_worktime_end' => '16:00',
            'tuesday_worktime_start' => '08:00',
            'tuesday_worktime_end' => '16:00',
            'wednesday_worktime_start' => '08:00',
            'wednesday_worktime_end' => '16:00',
            'thursday_worktime_start' => '08:00',
            'thursday_worktime_end' => '16:00',
            'friday_worktime_start' => '08:00',
            'friday_worktime_end' => '16:00',
        ]);

        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();

        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        return [$institution, $vendor, $accessToken];
    }

    private function createAssignmentEntry(
        Vendor $vendor,
        Institution $institution,
        Carbon $startAt,
        Carbon $endAt,
    ): VendorCalendarEntry {
        $sourceLanguage = ClassifierValue::factory()->language()->create();
        $destLanguage = ClassifierValue::factory()->language()->create();

        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $destLanguage->id,
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        return VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => $assignment->id,
            'start_at' => $startAt->utc(),
            'end_at' => $endAt->utc(),
        ]);
    }
}
