<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\AuthHelpers;
use Tests\TestCase;

class WorkflowControllerGetTaskTest extends TestCase
{
    private Institution $institution;

    private ClassifierValue $sourceLanguage;

    private ClassifierValue $destinationLanguage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $this->institution = Institution::factory()->create();
        $this->sourceLanguage = ClassifierValue::where('type', ClassifierValueType::Language)
            ->where('value', 'et-EE')
            ->firstOrFail();
        $this->destinationLanguage = ClassifierValue::where('type', ClassifierValueType::Language)
            ->whereNot('value', 'et-EE')
            ->firstOrFail();
    }

    public function test_vendor_sees_actual_project_translation_domain_when_viewing_task(): void
    {
        // GIVEN
        $vendorInstitutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id, 'name' => $this->institution->name])
            ->create();
        Vendor::factory()->create([
            'institution_user_id' => $vendorInstitutionUser->id,
            'company_name' => fake()->company(),
        ]);

        $project = $this->createProjectWithAssignment();
        $assignment = $project->subProjects->first()->assignments->first();

        $taskId = fake()->uuid();
        $executionId = fake()->uuid();
        $this->fakeCamundaForGetTask($taskId, $executionId, $assignment->id);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $vendorInstitutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/workflow/tasks/{$taskId}");

        // THEN
        $response->assertOk();
        $response->assertJsonPath(
            'data.assignment.subProject.project.translation_domain_classifier_value.id',
            $project->translation_domain_classifier_value_id
        );
    }

    private function createProjectWithAssignment(): Project
    {
        $project = Project::factory()->create([
            'institution_id' => $this->institution->id,
        ]);

        $subProject = SubProject::withoutEvents(fn () => SubProject::factory()->create([
            'project_id' => $project->id,
            'ext_id' => 'TEST-SP-' . fake()->unique()->numberBetween(1, 99999),
            'source_language_classifier_value_id' => $this->sourceLanguage->id,
            'destination_language_classifier_value_id' => $this->destinationLanguage->id,
        ]));

        Assignment::withoutEvents(fn () => Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
            'ext_id' => 'TEST-A-' . fake()->unique()->numberBetween(1, 99999),
        ]));

        return $project->load('subProjects.assignments');
    }

    private function fakeCamundaForGetTask(string $taskId, string $executionId, string $assignmentId): void
    {
        // Swap with a fresh Factory to clear the setUp's catch-all pattern stubs
        Http::swap(new HttpFactory());

        Http::fake(function ($request) use ($taskId, $executionId, $assignmentId) {
            if (str_contains($request->url(), '/token') || str_contains($request->url(), '/realms/')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }

            if (str_contains($request->url(), '/task') && $request->method() === 'POST') {
                return Http::response([[
                    'id' => $taskId,
                    'executionId' => $executionId,
                    'assignee' => null,
                    'processInstanceId' => fake()->uuid(),
                    'processDefinitionId' => fake()->uuid(),
                ]]);
            }

            if (str_contains($request->url(), '/variable-instance')) {
                return Http::response([
                    [
                        'name' => 'task_type',
                        'value' => 'DEFAULT',
                        'executionId' => $executionId,
                    ],
                    [
                        'name' => 'assignment_id',
                        'value' => $assignmentId,
                        'executionId' => $executionId,
                    ],
                ]);
            }

            return Http::response([], 200);
        });
    }
}
