<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\CandidateStatus;
use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use App\Enums\SkillCode;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Price;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Database\Seeders\CalendarSettingsSeeder;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\TestCase;

class CalendarProjectControllerUpdateTest extends TestCase
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

        $this->fakeCamundaWithActiveTasks();

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

    public function test_update_with_unchanged_fields_does_not_modify_candidates(): void
    {
        // GIVEN: a calendar project with a candidate and VCE
        [$project, $vendor, $accessToken] = $this->createCalendarProjectWithCandidate();

        $assignment = $project->subProjects->first()->assignments->first();
        $candidateBefore = Candidate::where('assignment_id', $assignment->id)->first();
        $vceBefore = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        // WHEN: update with all fields unchanged (no candidate_vendor_id, no use_external_vendor change)
        $payload = $this->createUpdatePayload($project);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}", $payload);

        // THEN: candidates and VCE remain untouched
        $response->assertOk();

        $candidateAfter = Candidate::where('assignment_id', $assignment->id)->first();
        $vceAfter = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertEquals($candidateBefore->id, $candidateAfter->id);
        $this->assertEquals($candidateBefore->vendor_id, $candidateAfter->vendor_id);
        $this->assertEquals($vceBefore->id, $vceAfter->id);
    }

    public function test_update_with_new_candidate_vendor_id_reassigns_vendor(): void
    {
        // GIVEN: a calendar project with vendor A
        [$project, $vendorA, $accessToken] = $this->createCalendarProjectWithCandidate();

        $assignment = $project->subProjects->first()->assignments->first();

        // Create a second available vendor
        $vendorB = $this->createVendorInInstitution();

        // WHEN: update with different candidate_vendor_id
        $payload = $this->createUpdatePayload($project, [
            'candidate_vendor_id' => $vendorB->id,
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}", $payload);

        // THEN: vendor B is now the candidate
        $response->assertOk();

        $candidate = Candidate::where('assignment_id', $assignment->id)
            ->where('status', '!=', CandidateStatus::Declined)
            ->first();
        $this->assertNotNull($candidate);
        $this->assertEquals($vendorB->id, $candidate->vendor_id);

        $vce = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();
        $this->assertNotNull($vce);
        $this->assertEquals($vendorB->id, $vce->vendor_id);
    }

    public function test_update_with_same_candidate_vendor_id_as_vce_is_noop(): void
    {
        // GIVEN: a calendar project with vendor A
        [$project, $vendor, $accessToken] = $this->createCalendarProjectWithCandidate();

        $assignment = $project->subProjects->first()->assignments->first();
        $candidateBefore = Candidate::where('assignment_id', $assignment->id)->first();
        $vceBefore = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        // WHEN: update with same candidate_vendor_id as current VCE
        $payload = $this->createUpdatePayload($project, [
            'candidate_vendor_id' => $vendor->id,
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}", $payload);

        // THEN: no changes to candidates or VCE
        $response->assertOk();

        $candidateAfter = Candidate::where('assignment_id', $assignment->id)->first();
        $vceAfter = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        $this->assertEquals($candidateBefore->id, $candidateAfter->id);
        $this->assertEquals($vceBefore->id, $vceAfter->id);
    }

    public function test_toggle_use_external_vendor_true_runs_external_cascade(): void
    {
        // GIVEN: a calendar project with internal vendor
        [$project, $internalVendor, $accessToken] = $this->createCalendarProjectWithCandidate();

        $assignment = $project->subProjects->first()->assignments->first();

        // Create an external vendor with coverage and pricing
        $externalVendor = $this->createVendorWithCoverage(internal: false);
        $this->createCalendarImport($externalVendor);
        $this->refreshView();

        // WHEN: toggle use_external_vendor to true
        $payload = $this->createUpdatePayload($project, [
            'use_external_vendor' => true,
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}", $payload);

        // THEN: external vendor is now a candidate
        $response->assertOk();

        $project->refresh();
        $this->assertTrue($project->use_external_vendor);

        // Old internal candidate was rejected
        $candidates = Candidate::where('assignment_id', $assignment->id)
            ->where('status', '!=', CandidateStatus::Declined)
            ->get();

        $this->assertTrue($candidates->contains('vendor_id', $externalVendor->id));
    }

    public function test_update_timeframe_syncs_vce(): void
    {
        // GIVEN: a calendar project with a candidate and VCE
        [$project, $vendor, $accessToken] = $this->createCalendarProjectWithCandidate();

        $assignment = $project->subProjects->first()->assignments->first();
        $newStart = Carbon::tomorrow()->setHour(14)->utc();
        $newEnd = Carbon::tomorrow()->setHour(15)->utc();

        // WHEN: update timeframe
        $payload = $this->createUpdatePayload($project, [
            'event_start_at' => $newStart->toIso8601ZuluString(),
            'event_end_at' => $newEnd->toIso8601ZuluString(),
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}", $payload);

        // THEN: VCE updated with new times
        $response->assertOk();

        $vce = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();
        $this->assertNotNull($vce);
        $this->assertEquals($newStart->toDateTimeString(), $vce->start_at->toDateTimeString());
        $this->assertEquals($newEnd->toDateTimeString(), $vce->end_at->toDateTimeString());
    }

    /**
     * Create a calendar project via API and return [project, vendor, accessToken].
     *
     * @return array{0: Project, 1: Vendor, 2: string}
     */
    private function createCalendarProjectWithCandidate(array $projectOverrides = []): array
    {
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
                PrivilegeKey::ChangeClient->value,
                PrivilegeKey::ChangeProjectManager->value,
            ],
        ]);

        $storePayload = $this->createCalendarStorePayload([
            'candidate_vendor_id' => $vendor->id,
            'manager_institution_user_id' => $actingUser->id,
            ...$projectOverrides,
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/projects', $storePayload);

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $project->load('subProjects.assignments');

        return [$project, $vendor, $accessToken];
    }

    private function createCalendarStorePayload(array $overrides = []): array
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

    private function createUpdatePayload(Project $project, array $overrides = []): array
    {
        $payload = array_filter([
            'type_classifier_value_id' => $project->type_classifier_value_id,
            'translation_domain_classifier_value_id' => $project->translation_domain_classifier_value_id,
            'manager_institution_user_id' => $project->manager_institution_user_id,
            'client_institution_user_id' => $project->client_institution_user_id,
            'reference_number' => $project->reference_number,
            'comments' => $project->comments,
            'event_start_at' => $project->event_start_at->toIso8601ZuluString(),
            'event_end_at' => $project->event_end_at->toIso8601ZuluString(),
            'service_type' => $project->service_type->value,
            'location' => $project->location,
            'destination_language_classifier_value_ids' => $project->subProjects->pluck('destination_language_classifier_value_id')->toArray(),
            'source_language_classifier_value_id' => $project->subProjects->first()->source_language_classifier_value_id,
        ], fn ($value) => $value !== null);

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

    private function fakeCamundaWithActiveTasks(): void
    {
        $executionId = fake()->uuid();
        $taskId = fake()->uuid();

        // Swap with a fresh Factory to clear the setUp's catch-all pattern stubs
        Http::swap(new HttpFactory());

        Http::fake(function ($request) use ($executionId, $taskId) {
            if (str_contains($request->url(), '/token') || str_contains($request->url(), '/realms/')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }

            if (str_contains($request->url(), '/process-instance')) {
                return Http::response([[
                    'id' => fake()->uuid(),
                    'definitionId' => fake()->uuid(),
                    'businessKey' => fake()->uuid(),
                ]], 200);
            }

            if (str_contains($request->url(), '/task/count')) {
                return Http::response(['count' => 1], 200);
            }

            if (str_contains($request->url(), '/task')) {
                return Http::response([[
                    'id' => $taskId,
                    'executionId' => $executionId,
                ]], 200);
            }

            if (str_contains($request->url(), '/variable-instance')) {
                $assignment = Assignment::query()->latest('created_at')->first();
                if ($assignment) {
                    return Http::response([
                        [
                            'name' => 'assignment_id',
                            'value' => $assignment->id,
                            'executionId' => $executionId,
                        ],
                    ], 200);
                }

                return Http::response([], 200);
            }

            return Http::response([
                'id' => fake()->uuid(),
                'definitionId' => fake()->uuid(),
                'businessKey' => fake()->uuid(),
            ], 200);
        });
    }
}
