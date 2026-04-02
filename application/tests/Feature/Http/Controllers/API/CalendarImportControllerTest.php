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
