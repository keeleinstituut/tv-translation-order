<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\CalendarSetting;
use App\Models\Candidate;
use App\Models\VendorSkillLanguage;
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

class CalendarProjectControllerStoreTest extends TestCase
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
        CalendarSetting::create([
            'institution_id' => $this->institution->id,
            'reaction_time_minutes' => 30,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'default_project_type_id' => null,
        ]);
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

    public function test_calendar_project_created_with_candidate_vendor_id_creates_candidate_and_calendar_entry(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
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
        $payload = $this->createCalendarPayload(['candidate_vendor_id' => $vendor->id]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertNotNull($project->event_start_at);
        $this->assertNotNull($project->event_end_at);
        $this->assertEquals($payload['service_type'], $project->service_type->value);
        $this->assertEquals($payload['location'], $project->location);
        $this->assertNull($project->deadline_at);

        $assignment = $project->subProjects->first()->assignments->first();
        $this->assertNotNull($assignment);

        // Candidate created for the vendor
        $candidate = Candidate::where('assignment_id', $assignment->id)
            ->where('vendor_id', $vendor->id)
            ->first();
        $this->assertNotNull($candidate);
        $this->assertEquals(1, $candidate->position);

        // Calendar entry created
        $calendarEntry = VendorCalendarEntry::where('assignment_id', $assignment->id)
            ->where('vendor_id', $vendor->id)
            ->first();
        $this->assertNotNull($calendarEntry);
        $this->assertEquals($project->event_start_at->toDateTimeString(), $calendarEntry->start_at->toDateTimeString());
        $this->assertEquals($project->event_end_at->toDateTimeString(), $calendarEntry->end_at->toDateTimeString());
    }

    public function test_calendar_project_without_vendor_runs_slot_matching(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorWithCoverage(internal: true);
        $this->createCalendarImport($vendor);

        $dayName = strtolower(Carbon::tomorrow()->utc()->format('l'));
        $this->institution->forceFill([
            'worktime_timezone' => 'UTC',
            "{$dayName}_worktime_start" => '09:00',
            "{$dayName}_worktime_end" => '18:00',
        ])->save();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);
        $payload = $this->createCalendarPayload();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();

        // Internal vendor found and stored as candidate
        $candidate = Candidate::where('assignment_id', $assignment->id)->first();
        $this->assertNotNull($candidate);
        $this->assertEquals($vendor->id, $candidate->vendor_id);
    }

    public function test_calendar_project_defaults_source_language_to_et(): void
    {
        // GIVEN
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
        $payload = $this->createCalendarPayload(['candidate_vendor_id' => $this->createVendorInInstitution()->id]);
        // source_language_classifier_value_id is not sent
        $this->assertArrayNotHasKey('source_language_classifier_value_id', $payload);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $subProject = $project->subProjects->first();
        $this->assertEquals($this->sourceLanguageET->id, $subProject->source_language_classifier_value_id);
    }

    public function test_calendar_project_restricts_destination_languages_to_one(): void
    {
        // GIVEN
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
        $otherLanguage = ClassifierValue::factory()->language()->create();
        $payload = $this->createCalendarPayload([
            'candidate_vendor_id' => $this->createVendorInInstitution()->id,
            'destination_language_classifier_value_ids' => [
                $this->destinationLanguage->id,
                $otherLanguage->id,
            ],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_calendar_project_rejects_deadline_at(): void
    {
        // GIVEN
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
        $payload = [
            ...$this->createCalendarPayload(['candidate_vendor_id' => $this->createVendorInInstitution()->id]),
            'deadline_at' => Carbon::tomorrow()->toIso8601ZuluString(),
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_calendar_project_rejects_source_files(): void
    {
        // GIVEN
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
        $payload = [
            ...$this->createCalendarPayload(['candidate_vendor_id' => $this->createVendorInInstitution()->id]),
            'source_files' => [\Illuminate\Http\UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf')],
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_calendar_project_requires_event_end_at(): void
    {
        // GIVEN
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
        $payload = $this->createCalendarPayload(['candidate_vendor_id' => $this->createVendorInInstitution()->id]);
        unset($payload['event_end_at']);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_calendar_project_rejects_event_end_before_event_start(): void
    {
        // GIVEN
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
        $payload = $this->createCalendarPayload([
            'candidate_vendor_id' => $this->createVendorInInstitution()->id,
            'event_start_at' => Carbon::tomorrow()->setHour(11)->toIso8601ZuluString(),
            'event_end_at' => Carbon::tomorrow()->setHour(10)->toIso8601ZuluString(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_candidate_vendor_id_rejected_without_manage_project_privilege(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = $this->createCalendarPayload(['candidate_vendor_id' => $vendor->id]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
    }

    public function test_tpm_gets_422_when_candidate_vendor_not_available(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
        // Block the vendor's slot with an existing entry
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => Carbon::tomorrow()->setHour(9)->utc(),
            'end_at' => Carbon::tomorrow()->setHour(12)->utc(),
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
        $payload = $this->createCalendarPayload(['candidate_vendor_id' => $vendor->id]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['candidate_vendor_id']);
        $this->assertDatabaseMissing('projects', ['institution_id' => $this->institution->id]);
    }

    public function test_client_creates_project_without_candidates_when_no_vendor_available(): void
    {
        // GIVEN
        // No vendors with language coverage and calendar import set up
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = $this->createCalendarPayload();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();

        $this->assertDatabaseMissing('candidates', ['assignment_id' => $assignment->id]);
        $this->assertDatabaseMissing('vendor_calendar_entries', ['assignment_id' => $assignment->id]);
    }

    public function test_client_creates_calendar_project_without_vendor_when_event_is_outside_vendor_working_hours(): void
    {
        // GIVEN
        // A future weekday is chosen deterministically so the day-of-week worktime fields apply.
        $eventDate = Carbon::parse('next monday')->utc();
        $dayName = strtolower($eventDate->format('l'));

        // Institution defines working hours 09:00-18:00 UTC for the event day
        // (this is the resolver's fallback when the vendor's own worktime is empty).
        $this->institution->forceFill([
            'worktime_timezone' => 'UTC',
            "{$dayName}_worktime_start" => '09:00',
            "{$dayName}_worktime_end" => '18:00',
        ])->save();

        // Vendor matches every other matching criterion: internal, language coverage,
        // calendar imported, no conflicting entries, no emergency schedule.
        $vendor = $this->createVendorWithCoverage(internal: true);
        $this->createCalendarImport($vendor, $eventDate);

        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);

        // Event is 30 minutes long at 19:00 — outside the 09:00-18:00 window.
        $payload = $this->createCalendarPayload([
            'event_start_at' => $eventDate->copy()->setTime(19, 0)->toIso8601ZuluString(),
            'event_end_at' => $eventDate->copy()->setTime(19, 30)->toIso8601ZuluString(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        // Project is still created — the client request succeeds.
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertTrue($project->is_calendar_project);

        $assignment = $project->subProjects->first()->assignments->first();
        $this->assertNotNull($assignment, 'Assignment must exist even when no vendor is matched');

        // No vendor was auto-assigned because the only matching vendor is outside working hours.
        $this->assertDatabaseMissing('candidates', ['assignment_id' => $assignment->id]);
        $this->assertDatabaseMissing('vendor_calendar_entries', ['assignment_id' => $assignment->id]);
    }

    public function test_client_creates_project_without_candidates_when_candidate_vendor_not_available(): void
    {
        // GIVEN
        // Clients cannot pass candidate_vendor_id (it's rejected by validation).
        // Test: client with no available vendors → project created, no candidates.
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorWithCoverage(internal: true);
        $this->createCalendarImport($vendor);

        // Block the vendor so auto matching finds nothing
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => Carbon::tomorrow()->setHour(9)->utc(),
            'end_at' => Carbon::tomorrow()->setHour(12)->utc(),
        ]);
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = $this->createCalendarPayload();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();

        $this->assertDatabaseMissing('candidates', ['assignment_id' => $assignment->id]);
        $this->assertDatabaseMissing('vendor_calendar_entries', ['assignment_id' => $assignment->id]);
    }

    public function test_prebook_converted_on_project_creation(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();
        // Create a prebook for the acting user overlapping the event slot
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
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = $this->createCalendarPayload([
            'event_start_at' => $eventStart->toIso8601ZuluString(),
            'event_end_at' => $eventEnd->toIso8601ZuluString(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $assignment = $project->subProjects->first()->assignments->first();

        // Candidate created with prebook's vendor
        $candidate = Candidate::where('assignment_id', $assignment->id)
            ->where('vendor_id', $vendor->id)
            ->first();
        $this->assertNotNull($candidate);

        // Prebook converted to assignment entry
        $prebook->refresh();
        $this->assertEquals($assignment->id, $prebook->assignment_id);
        $this->assertNull($prebook->prebook_institution_user_id);
        $this->assertNull($prebook->prebook_at);
    }

    public function test_acting_users_own_prebook_does_not_block_vendor_availability(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();
        // Acting user has a prebook for this vendor/slot — should be converted, not treated as blocking
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
        $payload = $this->createCalendarPayload([
            'event_start_at' => $eventStart->toIso8601ZuluString(),
            'event_end_at' => $eventEnd->toIso8601ZuluString(),
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();
        // Prebook was converted, not treated as a blocking conflict
        $prebook->refresh();
        $this->assertNotNull($prebook->assignment_id);
    }

    public function test_tpm_creates_calendar_project_with_all_specified_fields_stored_correctly(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->createWithPrivileges(PrivilegeKey::CreateProject);
        $vendor = $this->createVendorInInstitution();
        $eventStart = Carbon::tomorrow()->setHour(10)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(11)->utc();
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
        $payload = [
            'is_calendar_project' => true,
            'type_classifier_value_id' => $this->projectTypeId,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()->id,
            'client_institution_user_id' => $clientUser->id,
            'reference_number' => 'REF-2026-001',
            'destination_language_classifier_value_ids' => [$this->destinationLanguage->id],
            'event_start_at' => $eventStart->toIso8601ZuluString(),
            'event_end_at' => $eventEnd->toIso8601ZuluString(),
            'candidate_vendor_id' => $vendor->id,
            'service_type' => ServiceType::OnSite->value,
            'location' => 'Tallinn, Estonia',
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));

        $this->assertEquals('REF-2026-001', $project->reference_number);
        $this->assertEquals($clientUser->id, $project->client_institution_user_id);
        $this->assertEquals($eventStart->toDateTimeString(), $project->event_start_at->toDateTimeString());
        $this->assertEquals($eventEnd->toDateTimeString(), $project->event_end_at->toDateTimeString());
        $this->assertEquals(ServiceType::OnSite, $project->service_type);
        $this->assertEquals('Tallinn, Estonia', $project->location);
        $this->assertTrue($project->is_calendar_project);
        $this->assertNull($project->deadline_at);

        $subProject = $project->subProjects->first();
        $this->assertEquals($this->destinationLanguage->id, $subProject->destination_language_classifier_value_id);
    }

    public function test_client_creates_calendar_project_with_all_specified_fields_stored_correctly(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $clientUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->createWithPrivileges(PrivilegeKey::CreateProject);
        $eventStart = Carbon::tomorrow()->setHour(14)->utc();
        $eventEnd = Carbon::tomorrow()->setHour(15)->utc();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = [
            'is_calendar_project' => true,
            'type_classifier_value_id' => $this->projectTypeId,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()->id,
            'client_institution_user_id' => $clientUser->id,
            'reference_number' => 'REF-2026-002',
            'destination_language_classifier_value_ids' => [$this->destinationLanguage->id],
            'event_start_at' => $eventStart->toIso8601ZuluString(),
            'event_end_at' => $eventEnd->toIso8601ZuluString(),
            'service_type' => ServiceType::Remote->value,
            'meeting_link' => 'https://meet.example.com/client-test',
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));

        $this->assertEquals('REF-2026-002', $project->reference_number);
        $this->assertEquals($clientUser->id, $project->client_institution_user_id);
        $this->assertEquals($eventStart->toDateTimeString(), $project->event_start_at->toDateTimeString());
        $this->assertEquals($eventEnd->toDateTimeString(), $project->event_end_at->toDateTimeString());
        $this->assertEquals(ServiceType::Remote, $project->service_type);
        $this->assertEquals('https://meet.example.com/client-test', $project->meeting_link);
        $this->assertTrue($project->is_calendar_project);
        $this->assertNull($project->deadline_at);

        $subProject = $project->subProjects->first();
        $this->assertEquals($this->destinationLanguage->id, $subProject->destination_language_classifier_value_id);
    }

    public function test_calendar_project_derived_from_type_without_is_calendar_project_flag(): void
    {
        // GIVEN
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
        $vendor = $this->createVendorInInstitution();
        $payload = $this->createCalendarPayload(['candidate_vendor_id' => $vendor->id]);
        unset($payload['is_calendar_project']);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertTrue($project->is_calendar_project);
    }

    public function test_non_calendar_type_results_in_is_calendar_project_false(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);
        $nonCalendarTypeId = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) {
            $query->where('type', ClassifierValueType::ProjectType->value)
                ->where('value', 'TRANSLATION');
        })->firstOrFail()->type_classifier_value_id;

        $payload = [
            'type_classifier_value_id' => $nonCalendarTypeId,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()->id,
            'client_institution_user_id' => InstitutionUser::factory()
                ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
                ->createWithPrivileges(PrivilegeKey::CreateProject)->id,
            'source_language_classifier_value_id' => $this->sourceLanguageET->id,
            'destination_language_classifier_value_ids' => [$this->destinationLanguage->id],
            'deadline_at' => Carbon::tomorrow()->toIso8601ZuluString(),
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertFalse($project->is_calendar_project);
    }

    public function test_is_calendar_project_true_with_non_calendar_type_rejected(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);
        $nonCalendarTypeId = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) {
            $query->where('type', ClassifierValueType::ProjectType->value)
                ->where('value', 'TRANSLATION');
        })->firstOrFail()->type_classifier_value_id;

        $payload = [
            'is_calendar_project' => true,
            'type_classifier_value_id' => $nonCalendarTypeId,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()->id,
            'client_institution_user_id' => InstitutionUser::factory()
                ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
                ->createWithPrivileges(PrivilegeKey::CreateProject)->id,
            'destination_language_classifier_value_ids' => [$this->destinationLanguage->id],
            'event_start_at' => Carbon::tomorrow()->setHour(10)->toIso8601ZuluString(),
            'event_end_at' => Carbon::tomorrow()->setHour(11)->toIso8601ZuluString(),
            'service_type' => ServiceType::OnSite->value,
            'location' => 'Tallinn',
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['is_calendar_project']);
    }

    public function test_use_external_vendor_skips_availability_check(): void
    {
        // GIVEN
        // No internal vendors with coverage set up — would normally fail auto matching
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
        $payload = $this->createCalendarPayload(['use_external_vendor' => true]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        // Project created even though no internal vendor is available
        $response->assertCreated();
    }

    public function test_calendar_project_auto_assigns_manager_when_acting_user_has_receive_project_privilege(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ReceiveProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = $this->createCalendarPayload();
        // No manager_institution_user_id in payload

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertEquals($actingUser->id, $project->manager_institution_user_id);
        $this->assertEquals(ProjectStatus::Registered, $project->status);
    }

    public function test_calendar_project_does_not_override_explicit_manager_when_acting_user_has_receive_project(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $explicitManager = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->createWithPrivileges(PrivilegeKey::ReceiveProject);
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ReceiveProject->value,
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);
        $payload = $this->createCalendarPayload([
            'manager_institution_user_id' => $explicitManager->id,
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertEquals($explicitManager->id, $project->manager_institution_user_id);
    }

    public function test_calendar_project_does_not_auto_assign_manager_without_receive_project_privilege(): void
    {
        // GIVEN
        $actingUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $actingUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [
                PrivilegeKey::CreateProject->value,
                PrivilegeKey::ChangeClient->value,
            ],
        ]);
        $payload = $this->createCalendarPayload();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $payload);

        // THEN
        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertNull($project->manager_institution_user_id);
        $this->assertEquals(ProjectStatus::New, $project->status);
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
        VendorSkillLanguage::factory()->create([
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
}
