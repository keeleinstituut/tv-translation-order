<?php

namespace tests\Feature\Integration\Sync;

use App\Enums\VendorCalendarEntryType;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\ApiResponseHelpers;
use Tests\TestCase;

class InstitutionUserVacationSyncTest extends TestCase
{
    use ApiResponseHelpers;

    public function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_user_vacation_creates_vce_with_institution_user_vacation_id(): void
    {
        $vendor = Vendor::factory()->create();
        $vacationId = Str::orderedUuid()->toString();

        $responseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [
                    [
                        'id' => $vacationId,
                        'institution_user_id' => $vendor->institution_user_id,
                        'start_date' => '2023-06-10',
                        'end_date' => '2023-06-15',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
                'institution_vacations' => [],
            ],
        );

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($responseData),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        $entry = VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->first();
        $this->assertNotNull($entry);
        $this->assertEquals($vacationId, $entry->institution_user_vacation_id);
        $this->assertNull($entry->institution_vacation_id);
        $this->assertEquals(VendorCalendarEntryType::Vacation, $entry->type);
    }

    public function test_institution_vacation_creates_vce_with_institution_vacation_id(): void
    {
        $vendor = Vendor::factory()->create();
        $vacationId = Str::orderedUuid()->toString();

        $responseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [],
                'institution_vacations' => [
                    [
                        'id' => $vacationId,
                        'institution_id' => Str::orderedUuid()->toString(),
                        'start_date' => '2023-06-20',
                        'end_date' => '2023-06-25',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
            ],
        );

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($responseData),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        $entry = VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->first();
        $this->assertNotNull($entry);
        $this->assertNull($entry->institution_user_vacation_id);
        $this->assertEquals($vacationId, $entry->institution_vacation_id);
    }

    public function test_resync_updates_vacation_dates_in_place(): void
    {
        $vendor = Vendor::factory()->create();
        $vacationId = Str::orderedUuid()->toString();

        $firstResponseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [
                    [
                        'id' => $vacationId,
                        'institution_user_id' => $vendor->institution_user_id,
                        'start_date' => '2023-06-10',
                        'end_date' => '2023-06-15',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
                'institution_vacations' => [],
            ],
        );

        $secondResponseData = $firstResponseData;
        $secondResponseData['vacations']['institution_user_vacations'][0]['start_date'] = '2023-06-12';
        $secondResponseData['vacations']['institution_user_vacations'][0]['end_date'] = '2023-06-18';

        $responses = [$firstResponseData, $secondResponseData];
        $callIndex = 0;

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institution-users/*' => function () use (&$callIndex, $responses) {
                return Http::response(['data' => $responses[$callIndex++]]);
            },
        ]);

        // First sync
        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        $originalEntry = VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->first();
        $this->assertNotNull($originalEntry);

        // Second sync with changed dates
        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        // Should still be exactly one vacation entry (upserted, not duplicated)
        $entries = VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->get();
        $this->assertCount(1, $entries);

        $updatedEntry = $entries->first();
        $this->assertEquals($vacationId, $updatedEntry->institution_user_vacation_id);
        $this->assertEquals(
            Carbon::parse('2023-06-12')->startOfDay()->utc()->toDateTimeString(),
            $updatedEntry->start_at->toDateTimeString()
        );
    }

    public function test_resync_deletes_future_vacation_not_in_incoming_set(): void
    {
        $vendor = Vendor::factory()->create();
        $vacationId = Str::orderedUuid()->toString();

        $firstResponseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [
                    [
                        'id' => $vacationId,
                        'institution_user_id' => $vendor->institution_user_id,
                        'start_date' => '2023-06-10',
                        'end_date' => '2023-06-15',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
                'institution_vacations' => [],
            ],
        );

        $secondResponseData = $firstResponseData;
        $secondResponseData['vacations']['institution_user_vacations'] = [];

        $responses = [$firstResponseData, $secondResponseData];
        $callIndex = 0;

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institution-users/*' => function () use (&$callIndex, $responses) {
                return Http::response(['data' => $responses[$callIndex++]]);
            },
        ]);

        // First sync — one future vacation
        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        $this->assertCount(1, VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->get());

        // Second sync — vacation removed from response
        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        // Future vacation should be deleted
        $this->assertCount(0, VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->get());
    }

    public function test_resync_preserves_past_vacation_not_in_incoming_set(): void
    {
        $vendor = Vendor::factory()->create();
        $vacationId = Str::orderedUuid()->toString();

        // Directly create a past vacation VCE row (already ended)
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => Carbon::parse('2023-05-01')->startOfDay()->utc(),
            'end_at' => Carbon::parse('2023-05-06')->startOfDay()->utc(),
            'institution_user_vacation_id' => $vacationId,
        ]);

        // Sync with empty vacations (past vacation not in incoming set)
        $responseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [],
                'institution_vacations' => [],
            ],
        );

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($responseData),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        // Past vacation should be preserved
        $this->assertCount(1, VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->get());
    }

    public function test_sync_stores_vacation_source_ids_in_jsonb(): void
    {
        $vendor = Vendor::factory()->create();
        $userVacationId = Str::orderedUuid()->toString();
        $instVacationId = Str::orderedUuid()->toString();

        $responseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [
                    [
                        'id' => $userVacationId,
                        'institution_user_id' => $vendor->institution_user_id,
                        'start_date' => '2023-06-10',
                        'end_date' => '2023-06-15',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
                'institution_vacations' => [
                    [
                        'id' => $instVacationId,
                        'institution_id' => Str::orderedUuid()->toString(),
                        'start_date' => '2023-06-20',
                        'end_date' => '2023-06-25',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
            ],
        );

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($responseData),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        $institutionUser = InstitutionUser::find($vendor->institution_user_id);
        $vacations = collect($institutionUser->vacations);

        $this->assertCount(2, $vacations);

        $userVacation = $vacations->firstWhere('institution_user_vacation_id', $userVacationId);
        $this->assertNotNull($userVacation);
        $this->assertEquals($userVacationId, $userVacation['id']);
        $this->assertNull($userVacation['institution_vacation_id']);

        $instVacation = $vacations->firstWhere('institution_vacation_id', $instVacationId);
        $this->assertNotNull($instVacation);
        $this->assertEquals($instVacationId, $instVacation['id']);
        $this->assertNull($instVacation['institution_user_vacation_id']);
    }

    public function test_sync_creates_separate_vce_rows_for_each_vacation_type(): void
    {
        $vendor = Vendor::factory()->create();
        $userVacationId = Str::orderedUuid()->toString();
        $instVacationId = Str::orderedUuid()->toString();

        $responseData = $this->generateInstitutionUserResponseData(
            id: $vendor->institution_user_id,
            vacations: [
                'institution_user_vacations' => [
                    [
                        'id' => $userVacationId,
                        'institution_user_id' => $vendor->institution_user_id,
                        'start_date' => '2023-06-10',
                        'end_date' => '2023-06-15',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
                'institution_vacations' => [
                    [
                        'id' => $instVacationId,
                        'institution_id' => Str::orderedUuid()->toString(),
                        'start_date' => '2023-06-10',
                        'end_date' => '2023-06-15',
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
            ],
        );

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($responseData),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $vendor->institution_user_id])
            ->assertExitCode(0);

        $entries = VendorCalendarEntry::where('vendor_id', $vendor->id)->vacationsOnly()->get();
        $this->assertCount(2, $entries);
        $this->assertNotNull($entries->firstWhere('institution_user_vacation_id', $userVacationId));
        $this->assertNotNull($entries->firstWhere('institution_vacation_id', $instVacationId));
    }
}
