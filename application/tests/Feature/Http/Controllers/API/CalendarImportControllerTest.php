<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
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
