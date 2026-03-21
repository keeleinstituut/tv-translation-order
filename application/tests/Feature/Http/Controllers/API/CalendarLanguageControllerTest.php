<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\InstitutionUserPinnedLanguage;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Database\Seeders\CalendarSettingsSeeder;
use Illuminate\Support\Carbon;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarLanguageControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CalendarSettingsSeeder::class);
    }

    public function test_languages_returns_configured_institution_languages(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $today = Carbon::today();
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.main_languages')
            ->assertJsonCount(0, 'data.pinned_languages')
            ->assertJsonCount(0, 'data.project_languages')
            ->assertJson([
                'data' => [
                    'main_languages' => [
                        [
                            'language_id' => $language->id,
                            'language' => [
                                'id' => $language->id,
                                'type' => $language->type->value,
                                'value' => $language->value,
                                'name' => $language->name,
                                'meta' => $language->meta,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_languages_returns_only_languages_for_the_current_institution(): void
    {
        // GIVEN — two institutions each with one language; request scoped to first
        $institutionA = Institution::factory()->create();
        $institutionB = Institution::factory()->create();
        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        InstitutionMainLanguage::create(['institution_id' => $institutionA->id, 'language_id' => $languageA->id]);
        InstitutionMainLanguage::create(['institution_id' => $institutionB->id, 'language_id' => $languageB->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institutionA->id, 'name' => $institutionA->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $today = Carbon::today();
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — only languageA is returned in main_languages
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.main_languages')
            ->assertJson(['data' => ['main_languages' => [['language_id' => $languageA->id]]]]);
    }

    public function test_languages_requires_date_range_parameters(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN — missing required date params
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages');

        // THEN
        $response->assertStatus(422);
    }

    public function test_languages_includes_language_from_calendar_entries_in_date_range(): void
    {
        // GIVEN — institution with no main languages, but a calendar entry with an assignment in the range
        $institution = Institution::factory()->create();
        /** @var ClassifierValue $language */
        $language = ClassifierValue::factory()->language()->create();
        /** @var ClassifierValue $sourceLanguage */
        $sourceLanguage = ClassifierValue::factory()->language()->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $language->id,
        ]);
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $today = Carbon::today()->utc();
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => $assignment->id,
            'start_at' => $today->copy()->setHour(9),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.main_languages')
            ->assertJsonCount(1, 'data.project_languages')
            ->assertJson(['data' => ['project_languages' => [['id' => $language->id]]]]);
    }

    public function test_languages_excludes_calendar_entries_outside_date_range(): void
    {
        // GIVEN — calendar entry exists but outside the requested range
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();

        $sourceLanguage = ClassifierValue::factory()->language()->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $language->id,
        ]);
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $yesterday = Carbon::today()->utc()->subDay();
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => $assignment->id,
            'start_at' => $yesterday->copy()->setHour(9),
            'end_at' => $yesterday->copy()->setHour(11),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN — requesting today only
        $today = Carbon::today();
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — no project languages (entry outside range), no main languages
        $response
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.main_languages')
            ->assertJsonCount(0, 'data.project_languages');
    }

    public function test_languages_returns_pinned_languages_for_client(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $language = ClassifierValue::factory()->language()->create();

        $mainLanguage = InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLanguage->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::CreateProject->value],
        ]);

        // WHEN
        $today = Carbon::today();
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — language appears in main_languages and pinned_languages
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.main_languages')
            ->assertJsonCount(1, 'data.pinned_languages')
            ->assertJson([
                'data' => [
                    'main_languages' => [['language_id' => $language->id]],
                    'pinned_languages' => [['institution_main_language_id' => $mainLanguage->id]],
                ],
            ]);
    }

    public function test_languages_pinned_languages_reflects_current_user_only(): void
    {
        // GIVEN — two users in same institution, only userA pins the language
        $institution = Institution::factory()->create();
        $userA = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $userB = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $language = ClassifierValue::factory()->language()->create();

        $mainLanguage = InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $userA->id,
            'institution_main_language_id' => $mainLanguage->id,
        ]);

        // WHEN — request as userB (who has NOT pinned the language)
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $userB->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        $today = Carbon::today();
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — main language is present but pinned_languages is empty for userB
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.main_languages')
            ->assertJsonCount(0, 'data.pinned_languages')
            ->assertJson(['data' => ['main_languages' => [['language_id' => $language->id]]]]);
    }

    public function test_languages_returns_flat_project_language_list_for_vendor(): void
    {
        // GIVEN — vendor user with a calendar entry assigned within the date range
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $language = ClassifierValue::factory()->language()->create();
        $sourceLanguage = ClassifierValue::factory()->language()->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $language->id,
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $today = Carbon::today()->utc();
        VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'assignment_id' => $assignment->id,
            'start_at' => $today->copy()->setHour(9),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — vendor gets a flat ClassifierValue collection (no main/pinned keys)
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.project_languages')
            ->assertJson(['data' => ['project_languages' => [['id' => $language->id]]]]);
    }

    public function test_languages_vendor_sees_only_own_entries(): void
    {
        // GIVEN — two vendors with entries; request scoped to vendorA's user
        $institution = Institution::factory()->create();

        $userA = InstitutionUser::factory()
            ->setInstitution(['id' => $institution->id, 'name' => $institution->name])
            ->create();
        $vendorA = Vendor::factory()->create(['institution_user_id' => $userA->id]);

        $vendorB = Vendor::factory()->create();

        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();
        $sourceLanguage = ClassifierValue::factory()->language()->create();

        $project = Project::factory()->create(['institution_id' => $institution->id]);

        $subProjectA = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $languageA->id,
        ]);
        $subProjectB = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_id' => $languageB->id,
        ]);

        $assignmentA = Assignment::factory()->create([
            'sub_project_id' => $subProjectA->id,
            'assigned_vendor_id' => $vendorA->id,
        ]);
        $assignmentB = Assignment::factory()->create([
            'sub_project_id' => $subProjectB->id,
            'assigned_vendor_id' => $vendorB->id,
        ]);

        $today = Carbon::today()->utc();
        VendorCalendarEntry::create([
            'vendor_id' => $vendorA->id,
            'assignment_id' => $assignmentA->id,
            'start_at' => $today->copy()->setHour(9),
            'end_at' => $today->copy()->setHour(10),
        ]);
        VendorCalendarEntry::create([
            'vendor_id' => $vendorB->id,
            'assignment_id' => $assignmentB->id,
            'start_at' => $today->copy()->setHour(10),
            'end_at' => $today->copy()->setHour(11),
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $userA->id,
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/calendar/languages?date_from=' . $today->toDateString() . '&date_to=' . $today->toDateString());

        // THEN — only languageA (vendorA's language); languageB is excluded
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.project_languages')
            ->assertJson(['data' => ['project_languages' => [['id' => $languageA->id]]]]);
    }
}
