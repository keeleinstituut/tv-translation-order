<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ServiceType;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\CalendarSetting;
use App\Models\Candidate;
use App\Models\Price;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Database\Seeders\CalendarSettingsSeeder;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarProjectBufferTimeTest extends TestCase
{
    private Institution $institution;

    private ClassifierValue $destinationLanguage;

    private ClassifierValue $sourceLanguageET;

    private string $projectTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $this->seed(CalendarSettingsSeeder::class);

        $this->institution = Institution::factory()->create();
        $this->destinationLanguage = ClassifierValue::where('type', ClassifierValueType::Language)
            ->whereNot('value', 'et-EE')
            ->firstOrFail();
        $this->sourceLanguageET = ClassifierValue::where('type', ClassifierValueType::Language)
            ->where('value', 'et-EE')
            ->firstOrFail();

        $this->projectTypeId = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) {
            $query->where('type', ClassifierValueType::ProjectType->value)
                ->where('value', 'ORAL_TRANSLATION');
        })->firstOrFail()->type_classifier_value_id;
    }

    public function test_on_site_project_creates_calendar_entry_with_buffer(): void
    {
        $this->setBuffer(30, 15);
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();

        $response = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
                'service_type' => ServiceType::OnSite->value,
                'location' => 'Tallinn',
            ]));

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();
        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertNotNull($calendarEntry);
        $this->assertEquals(
            $eventStart->copy()->subMinutes(30)->toDateTimeString(),
            $calendarEntry->start_at->toDateTimeString(),
        );
        $this->assertEquals(
            $eventEnd->copy()->addMinutes(15)->toDateTimeString(),
            $calendarEntry->end_at->toDateTimeString(),
        );
    }

    public function test_remote_project_creates_calendar_entry_without_buffer(): void
    {
        $this->setBuffer(30, 15);
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();

        $response = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
                'service_type' => ServiceType::Remote->value,
                'meeting_link' => 'https://meet.example.com/test',
                'location' => null,
            ]));

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();
        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertNotNull($calendarEntry);
        $this->assertEquals($eventStart->toDateTimeString(), $calendarEntry->start_at->toDateTimeString());
        $this->assertEquals($eventEnd->toDateTimeString(), $calendarEntry->end_at->toDateTimeString());
    }

    public function test_zero_buffer_on_site_creates_entry_matching_event_times(): void
    {
        $this->setBuffer(0, 0);
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();

        $response = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
            ]));

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();
        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertNotNull($calendarEntry);
        $this->assertEquals($eventStart->toDateTimeString(), $calendarEntry->start_at->toDateTimeString());
        $this->assertEquals($eventEnd->toDateTimeString(), $calendarEntry->end_at->toDateTimeString());
    }

    public function test_buffer_prevents_overlapping_booking_in_buffer_zone(): void
    {
        $this->setBuffer(30, 30);
        $vendor = $this->createVendorInInstitution();

        // First booking: 10:00-11:00, entry becomes 09:30-11:30
        $response1 = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => Carbon::tomorrow()->setHour(10)->utc()->toIso8601ZuluString(),
                'event_end_at' => Carbon::tomorrow()->setHour(11)->utc()->toIso8601ZuluString(),
            ]));

        $response1->assertCreated();

        // Second booking: 11:00-12:00, with buffer would be 10:30-12:30, overlapping 09:30-11:30
        $vendor2 = $this->createVendorInInstitution();
        $response2 = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => Carbon::tomorrow()->setHour(11)->utc()->toIso8601ZuluString(),
                'event_end_at' => Carbon::tomorrow()->setHour(12)->utc()->toIso8601ZuluString(),
            ]));

        $response2->assertUnprocessable();
    }

    public function test_prebook_conversion_widens_entry_for_on_site(): void
    {
        $this->setBuffer(30, 15);
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();

        // Create a prebook with original (unbuffered) times
        $prebook = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => $eventStart,
            'end_at' => $eventEnd,
            'prebook_institution_user_id' => $actingUser->id,
            'prebook_at' => now(),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ManageProject->value,
                PrivilegeKey::ReceiveProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $this->createCalendarPayload([
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
            ]));

        $response->assertCreated();

        $prebook->refresh();
        $this->assertNotNull($prebook->assignment_id);
        $this->assertEquals(
            $eventStart->copy()->subMinutes(30)->toDateTimeString(),
            $prebook->start_at->toDateTimeString(),
        );
        $this->assertEquals(
            $eventEnd->copy()->addMinutes(15)->toDateTimeString(),
            $prebook->end_at->toDateTimeString(),
        );
    }

    public function test_prebook_is_released_when_project_created_via_explicit_vendor(): void
    {
        $this->setBuffer(0, 0);
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();

        // Create a prebook for a different time slot
        $prebook = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => Carbon::tomorrow()->setHour(14)->utc(),
            'end_at' => Carbon::tomorrow()->setHour(15)->utc(),
            'prebook_institution_user_id' => $actingUser->id,
            'prebook_at' => now(),
        ]);

        $anotherVendor = $this->createVendorInInstitution();

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ManageProject->value,
                PrivilegeKey::ReceiveProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $anotherVendor->id,
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
            ]));

        $response->assertCreated();

        $this->assertDatabaseMissing('vendor_calendar_entries', ['id' => $prebook->id]);
    }

    public function test_on_site_buffer_extends_before_working_hours(): void
    {
        $this->setBuffer(30, 0);
        $vendor = $this->createVendorInInstitution();

        // Event at 9:00-10:00, buffer_before=30min → entry starts at 8:30
        $eventStart = Carbon::tomorrow()->setHour(9)->setMinute(0)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(10)->setMinute(0)->utc();

        $response = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
            ]));

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();
        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertNotNull($calendarEntry);
        $this->assertEquals(
            $eventStart->copy()->subMinutes(30)->toDateTimeString(),
            $calendarEntry->start_at->toDateTimeString(),
        );
        $this->assertEquals($eventEnd->toDateTimeString(), $calendarEntry->end_at->toDateTimeString());
    }

    public function test_on_site_buffer_extends_after_working_hours(): void
    {
        $this->setBuffer(0, 30);
        $vendor = $this->createVendorInInstitution();

        // Event at 17:00-18:00, buffer_after=30min → entry ends at 18:30
        $eventStart = Carbon::tomorrow()->setHour(17)->setMinute(0)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(18)->setMinute(0)->utc();

        $response = $this->actAsTpm()
            ->postJson('/api/projects', $this->createCalendarPayload([
                'candidate_vendor_id' => $vendor->id,
                'event_start_at' => $eventStart->toIso8601ZuluString(),
                'event_end_at' => $eventEnd->toIso8601ZuluString(),
            ]));

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();
        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertNotNull($calendarEntry);
        $this->assertEquals($eventStart->toDateTimeString(), $calendarEntry->start_at->toDateTimeString());
        $this->assertEquals(
            $eventEnd->copy()->addMinutes(30)->toDateTimeString(),
            $calendarEntry->end_at->toDateTimeString(),
        );
    }

    private function setBuffer(int $before, int $after): void
    {
        CalendarSetting::updateOrCreate(
            ['institution_id' => $this->institution->id],
            [
                'buffer_before_minutes' => $before,
                'buffer_after_minutes' => $after,
                'reaction_time_seconds' => 30,
                'default_project_type_id' => ClassifierValue::where('type', ClassifierValueType::ProjectType)
                    ->where('value', 'ORAL_TRANSLATION')
                    ->firstOrFail()->id,
            ],
        );
    }

    private function actAsTpm(): static
    {
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ManageProject->value,
                PrivilegeKey::ReceiveProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);

        return $this->prepareAuthorizedRequest($accessToken);
    }

    private function createCalendarPayload(array $overrides = []): array
    {
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();

        $payload = [
            'is_calendar_project' => true,
            'type_classifier_value_id' => $this->projectTypeId,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()->id,
            'client_institution_user_id' => InstitutionUser::factory()
                ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
                ->createWithPrivileges(PrivilegeKey::CreateProject)->id,
            'destination_language_classifier_value_ids' => [$this->destinationLanguage->id],
            'event_start_at' => $eventStart->toIso8601ZuluString(),
            'event_end_at' => $eventEnd->toIso8601ZuluString(),
            'service_type' => ServiceType::OnSite->value,
            'location' => 'Tallinn',
        ];

        return array_merge($payload, $overrides);
    }

    private function createVendorInInstitution(): Vendor
    {
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();

        return Vendor::factory()->create([
            'institution_user_id' => $institutionUser->id,
        ]);
    }

    private function createVendorWithCoverage(bool $internal = true): Vendor
    {
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();

        $vendor = Vendor::factory()->create([
            'institution_user_id' => $institutionUser->id,
            'company_name' => $internal ? null : fake()->company(),
        ]);

        $skill = Skill::findByCode(SkillCode::OralInterpretation);
        Price::factory()->create([
            'vendor_id' => $vendor->id,
            'skill_id' => $skill->id,
            'src_lang_classifier_value_id' => $this->sourceLanguageET->id,
            'dst_lang_classifier_value_id' => $this->destinationLanguage->id,
        ]);

        return $vendor;
    }

    private function createCalendarImport(Vendor $vendor, ?Carbon $date = null): void
    {
        $date = $date ?? Carbon::tomorrow();

        DB::table('vendor_calendar_imports')->insert([
            'id' => Str::orderedUuid()->toString(),
            'vendor_id' => $vendor->id,
            'date_from' => $date->copy()->startOfMonth(),
            'date_to' => $date->copy()->endOfMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function refreshView(): void
    {
        DB::statement('REFRESH MATERIALIZED VIEW v_vendor_language_coverage');
    }
}
