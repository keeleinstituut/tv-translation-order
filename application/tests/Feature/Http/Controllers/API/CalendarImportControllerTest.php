<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\VendorCalendarImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarImportControllerTest extends TestCase
{
    private function makeIcsFile(array $events): UploadedFile
    {
        $vevents = '';
        foreach ($events as $event) {
            $dtstart = $event['dtstart'];
            $dtend = $event['dtend'];
            $summary = $event['summary'] ?? 'Busy';
            $vevents .= "BEGIN:VEVENT\r\nDTSTART:{$dtstart}\r\nDTEND:{$dtend}\r\nSUMMARY:{$summary}\r\nEND:VEVENT\r\n";
        }

        $content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//Test//EN\r\n{$vevents}END:VCALENDAR\r\n";

        return UploadedFile::fake()->createWithContent('calendar.ics', $content)->mimeType('text/calendar');
    }

    private function createVendorWithAuth(): array
    {
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        return [$vendor, $accessToken, $institution, $institutionUser];
    }


    public function test_store_imports_ics_file_and_creates_entries(): void
    {
        // GIVEN
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        $firstEventStart = Carbon::now()->utc()->addDay()->setTime(9, 0)->format('Ymd\THis\Z');
        $firstEventEnd = Carbon::now()->utc()->addDay()->setTime(10, 0)->format('Ymd\THis\Z');
        $secondEventStart = Carbon::now()->utc()->addDays(2)->setTime(14, 0)->format('Ymd\THis\Z');
        $secondEventEnd = Carbon::now()->utc()->addDays(2)->setTime(15, 0)->format('Ymd\THis\Z');
        $importEndDate = Carbon::now()->utc()->addMonth()->toDateString();

        $file = $this->makeIcsFile([
            ['dtstart' => $firstEventStart, 'dtend' => $firstEventEnd, 'summary' => 'Meeting'],
            ['dtstart' => $secondEventStart, 'dtend' => $secondEventEnd, 'summary' => 'Call'],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => $importEndDate,
                'file' => $file,
            ]);


        // THEN
        $response
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'vendor_id', 'date_from', 'date_to', 'events_count', 'created_at']])
            ->assertJson(['data' => ['vendor_id' => $vendor->id, 'events_count' => 2]]);

        $this->assertDatabaseCount('vendor_calendar_imports', 1);
        $this->assertDatabaseHas('vendor_calendar_imports', ['vendor_id' => $vendor->id]);

        $entries = VendorCalendarEntry::where('vendor_id', $vendor->id)->get();
        $this->assertCount(2, $entries);
        $this->assertNotNull($entries->first()->vendor_calendar_import_id);
    }

    public function test_store_reimport_skips_conflicting_entries_and_imports_new_ones(): void
    {
        // GIVEN — a vendor with an existing import
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        $overlappingStart = Carbon::now()->utc()->addDay()->setTime(9, 0);
        $overlappingEnd = Carbon::now()->utc()->addDay()->setTime(10, 0);
        $importEndDate = Carbon::now()->utc()->addMonth()->toDateString();

        $firstFile = $this->makeIcsFile([
            [
                'dtstart' => $overlappingStart->format('Ymd\THis\Z'),
                'dtend' => $overlappingEnd->format('Ymd\THis\Z'),
                'summary' => 'Original event',
            ],
        ]);

        $firstResponse = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => $importEndDate,
                'file' => $firstFile,
            ]);

        $firstResponse->assertStatus(201);
        $this->assertDatabaseCount('vendor_calendar_entries', 1);

        // WHEN — reimport with the same event (overlapping) plus a new non-overlapping event
        $newEventStart = Carbon::now()->utc()->addDays(3)->setTime(14, 0);
        $newEventEnd = Carbon::now()->utc()->addDays(3)->setTime(15, 0);

        $secondFile = $this->makeIcsFile([
            [
                'dtstart' => $overlappingStart->format('Ymd\THis\Z'),
                'dtend' => $overlappingEnd->format('Ymd\THis\Z'),
                'summary' => 'Duplicate event',
            ],
            [
                'dtstart' => $newEventStart->format('Ymd\THis\Z'),
                'dtend' => $newEventEnd->format('Ymd\THis\Z'),
                'summary' => 'New event',
            ],
        ]);

        $secondResponse = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => $importEndDate,
                'file' => $secondFile,
            ]);

        // THEN — the conflicting event is skipped, the new event is imported
        $secondResponse->assertStatus(201)
            ->assertJson(['data' => ['events_count' => 1]]);

        $entries = VendorCalendarEntry::where('vendor_id', $vendor->id)->get();
        $this->assertCount(2, $entries);
    }

    public function test_store_rejects_non_vendor_user(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        $file = $this->makeIcsFile([
            ['dtstart' => '20260401T090000Z', 'dtend' => '20260401T100000Z'],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => '2026-06-01',
                'file' => $file,
            ]);

        // THEN
        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        // GIVEN
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        // WHEN — empty payload
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', []);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['import_end_date', 'file']);
    }

    public function test_store_validates_import_end_date_must_be_future(): void
    {
        // GIVEN
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        $file = $this->makeIcsFile([
            ['dtstart' => '20260401T090000Z', 'dtend' => '20260401T100000Z'],
        ]);

        // WHEN — past date
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => '2020-01-01',
                'file' => $file,
            ]);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['import_end_date']);
    }

    public function test_store_correctly_handles_timezone_in_ics_events(): void
    {
        // GIVEN — an ICS file with Europe/Tallinn timezone (UTC+3 in summer)
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        $importEndDate = Carbon::now()->utc()->addYear()->toDateString();

        // Event at 11:30-12:30 Tallinn time on a summer date (UTC+3)
        // Should be stored as 08:30-09:30 UTC
        $content = "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//Test//Test//EN\r\n"
            . "X-WR-TIMEZONE:Europe/Tallinn\r\n"
            . "BEGIN:VTIMEZONE\r\n"
            . "TZID:Europe/Tallinn\r\n"
            . "BEGIN:DAYLIGHT\r\n"
            . "TZOFFSETFROM:+0200\r\n"
            . "TZOFFSETTO:+0300\r\n"
            . "DTSTART:19700329T030000\r\n"
            . "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n"
            . "END:DAYLIGHT\r\n"
            . "BEGIN:STANDARD\r\n"
            . "TZOFFSETFROM:+0300\r\n"
            . "TZOFFSETTO:+0200\r\n"
            . "DTSTART:19701025T040000\r\n"
            . "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n"
            . "END:STANDARD\r\n"
            . "END:VTIMEZONE\r\n"
            . "BEGIN:VEVENT\r\n"
            . "DTSTART;TZID=Europe/Tallinn:20260615T113000\r\n"
            . "DTEND;TZID=Europe/Tallinn:20260615T123000\r\n"
            . "SUMMARY:Morning meeting\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";

        $file = UploadedFile::fake()->createWithContent('calendar.ics', $content)->mimeType('text/calendar');

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => $importEndDate,
                'file' => $file,
            ]);

        // THEN
        $response->assertStatus(201)
            ->assertJson(['data' => ['events_count' => 1]]);

        $entry = VendorCalendarEntry::where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($entry);

        // 11:30 Tallinn (UTC+3) = 08:30 UTC
        $this->assertEquals('2026-06-15 08:30:00', $entry->start_at->format('Y-m-d H:i:s'));
        // 12:30 Tallinn (UTC+3) = 09:30 UTC
        $this->assertEquals('2026-06-15 09:30:00', $entry->end_at->format('Y-m-d H:i:s'));
    }

    public function test_index_returns_vendor_imports_newest_first_with_events_count(): void
    {
        // GIVEN — three imports with different date_from values and different numbers of entries
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();

        $oldest = $this->createImportWithEntry($vendor, $now->copy()->subMonths(3), $now->copy()->subMonths(2), entryCount: 1);
        $middle = $this->createImportWithEntry($vendor, $now->copy()->subMonth(), $now->copy()->addDay(), entryCount: 3);
        $newest = $this->createImportWithEntry($vendor, $now->copy()->addMonth(), $now->copy()->addMonths(2), entryCount: 2);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/import');

        // THEN
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'vendor_id', 'date_from', 'date_to', 'events_count', 'created_at']],
                'meta',
                'links',
            ]);

        $items = $response->json('data');
        $this->assertEquals([$newest->id, $middle->id, $oldest->id], array_column($items, 'id'));
        $this->assertEquals([2, 3, 1], array_column($items, 'events_count'));
    }

    public function test_index_does_not_leak_other_vendors_imports(): void
    {
        // GIVEN
        [$vendorA, $accessTokenA] = $this->createVendorWithAuth();
        [$vendorB] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();

        $vendorAImport = $this->createImportWithEntry($vendorA, $now->copy()->subDay(), $now->copy()->addMonth());
        $this->createImportWithEntry($vendorB, $now->copy()->subDay(), $now->copy()->addMonth());

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessTokenA)
            ->getJson('/api/calendar/import');

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals($vendorAImport->id, $response->json('data.0.id'));
    }

    public function test_index_filters_out_imports_entirely_before_window(): void
    {
        // GIVEN — import ends before the filter window starts
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();
        $this->createImportWithEntry($vendor, $now->copy()->subMonths(3), $now->copy()->subMonths(2));

        // WHEN — filter window starts a month later than the import's date_to
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/import?date_from='.$now->copy()->subMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_index_filters_out_imports_entirely_after_window(): void
    {
        // GIVEN — import starts after the filter window ends
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();
        $this->createImportWithEntry($vendor, $now->copy()->addMonths(2), $now->copy()->addMonths(3));

        // WHEN — filter window ends a month before the import's date_from
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/import?date_to='.$now->copy()->addMonth()->toDateString());

        // THEN
        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_index_returns_imports_that_overlap_the_window(): void
    {
        // GIVEN — three imports: before, straddling, after the filter window
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();

        $this->createImportWithEntry($vendor, $now->copy()->subMonths(3), $now->copy()->subMonths(2));
        $overlapping = $this->createImportWithEntry($vendor, $now->copy()->subDays(10), $now->copy()->addDays(10));
        $this->createImportWithEntry($vendor, $now->copy()->addMonths(2), $now->copy()->addMonths(3));

        $dateFrom = $now->copy()->subDays(5)->toDateString();
        $dateTo = $now->copy()->addDays(5)->toDateString();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/calendar/import?date_from={$dateFrom}&date_to={$dateTo}");

        // THEN
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals($overlapping->id, $response->json('data.0.id'));
    }

    public function test_index_without_filters_returns_all_imports_including_past(): void
    {
        // GIVEN
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();
        $this->createImportWithEntry($vendor, $now->copy()->subMonths(3), $now->copy()->subMonths(2));
        $this->createImportWithEntry($vendor, $now->copy()->addMonth(), $now->copy()->addMonths(2));

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/import');

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_index_rejects_non_vendor_user(): void
    {
        // GIVEN — institution user without a vendor row
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/import');

        // THEN
        $response->assertStatus(403);
    }

    public function test_index_validates_date_to_after_or_equal_date_from(): void
    {
        // GIVEN
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        // WHEN — date_to before date_from
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/import?date_from=2026-06-01&date_to=2026-05-01');

        // THEN
        $response->assertStatus(422)->assertJsonValidationErrors(['date_to']);
    }

    public function test_bulk_destroy_deletes_future_imports_with_their_entries_and_preserves_past(): void
    {
        // GIVEN — vendor with one past import, one ongoing, and one fully-future import, each with entries
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();

        $pastImport = $this->createImportWithEntry($vendor, $now->copy()->subMonths(2), $now->copy()->subMonth());
        $ongoingImport = $this->createImportWithEntry($vendor, $now->copy()->subDays(2), $now->copy()->addMonth());
        $futureImport = $this->createImportWithEntry($vendor, $now->copy()->addMonth(), $now->copy()->addMonths(2));

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/calendar/import/bulk');

        // THEN
        $response->assertStatus(204);

        // Past import + its entries preserved
        $this->assertDatabaseHas('vendor_calendar_imports', ['id' => $pastImport->id]);
        $this->assertDatabaseHas('vendor_calendar_entries', ['vendor_calendar_import_id' => $pastImport->id]);

        // Ongoing and future imports + all their entries are gone (cascade hard-delete)
        $this->assertDatabaseMissing('vendor_calendar_imports', ['id' => $ongoingImport->id]);
        $this->assertDatabaseMissing('vendor_calendar_imports', ['id' => $futureImport->id]);
        $this->assertDatabaseMissing('vendor_calendar_entries', ['vendor_calendar_import_id' => $ongoingImport->id]);
        $this->assertDatabaseMissing('vendor_calendar_entries', ['vendor_calendar_import_id' => $futureImport->id]);
    }

    public function test_bulk_destroy_does_not_touch_other_vendors_imports(): void
    {
        // GIVEN — two vendors, each with a future import
        [$vendorA, $accessTokenA] = $this->createVendorWithAuth();
        [$vendorB] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();

        $vendorAImport = $this->createImportWithEntry($vendorA, $now->copy()->addDay(), $now->copy()->addMonth());
        $vendorBImport = $this->createImportWithEntry($vendorB, $now->copy()->addDay(), $now->copy()->addMonth());

        // WHEN — vendor A calls the endpoint
        $response = $this->prepareAuthorizedRequest($accessTokenA)
            ->deleteJson('/api/calendar/import/bulk');

        // THEN — only vendor A's import is deleted
        $response->assertStatus(204);
        $this->assertDatabaseMissing('vendor_calendar_imports', ['id' => $vendorAImport->id]);
        $this->assertDatabaseHas('vendor_calendar_imports', ['id' => $vendorBImport->id]);
        $this->assertDatabaseHas('vendor_calendar_entries', ['vendor_calendar_import_id' => $vendorBImport->id]);
    }

    public function test_bulk_destroy_is_a_noop_when_vendor_has_no_future_imports(): void
    {
        // GIVEN — vendor with only a past import
        [$vendor, $accessToken] = $this->createVendorWithAuth();
        $now = Carbon::now()->utc();
        $pastImport = $this->createImportWithEntry($vendor, $now->copy()->subMonths(2), $now->copy()->subMonth());

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/calendar/import/bulk');

        // THEN
        $response->assertStatus(204);
        $this->assertDatabaseHas('vendor_calendar_imports', ['id' => $pastImport->id]);
        $this->assertDatabaseHas('vendor_calendar_entries', ['vendor_calendar_import_id' => $pastImport->id]);
    }

    public function test_bulk_destroy_rejects_non_vendor_user(): void
    {
        // GIVEN — institution user without a vendor row
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/calendar/import/bulk');

        // THEN — firstOrFail on vendor lookup returns 404
        $response->assertStatus(404);
    }

    private function createImportWithEntry(Vendor $vendor, Carbon $dateFrom, Carbon $dateTo, int $entryCount = 1): VendorCalendarImport
    {
        $import = VendorCalendarImport::create([
            'vendor_id' => $vendor->id,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        for ($i = 0; $i < $entryCount; $i++) {
            VendorCalendarEntry::create([
                'vendor_id' => $vendor->id,
                'start_at' => $dateFrom->copy()->addHours(1 + $i * 2),
                'end_at' => $dateFrom->copy()->addHours(2 + $i * 2),
                'vendor_calendar_import_id' => $import->id,
            ]);
        }

        return $import;
    }

    public function test_store_rejects_non_ics_file(): void
    {
        // GIVEN
        [$vendor, $accessToken] = $this->createVendorWithAuth();

        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/calendar/import', [
                'import_end_date' => '2026-06-01',
                'file' => $file,
            ]);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
