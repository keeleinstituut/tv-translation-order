<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\JobKey;
use App\Enums\PrivilegeKey;
use App\Http\Controllers\API\SubProjectController;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\JobDefinition;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Tests\AuthHelpers;
use Tests\TestCase;

class SubProjectControllerStartWorkflowTest extends TestCase
{
    private function makeSubProject(InstitutionUser $actingUser, string $typeCode, ?Carbon $deadlineAt, ?Carbon $eventStartAt): SubProject
    {
        $typeClassifierValueId = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) use ($typeCode) {
            $query->where('value', $typeCode);
        })->firstOrFail()->type_classifier_value_id;

        $projectTypeConfig = ProjectTypeConfig::where('type_classifier_value_id', $typeClassifierValueId)->firstOrFail();

        $jobDefinition = JobDefinition::firstOrCreate(
            ['project_type_config_id' => $projectTypeConfig->id, 'job_key' => JobKey::JOB_TRANSLATION],
            ['multi_assignments_enabled' => false, 'linking_with_cat_tool_jobs_enabled' => false, 'sequence' => 1]
        );

        $project = Project::factory()->create([
            'institution_id' => $actingUser->institution['id'],
            'type_classifier_value_id' => $typeClassifierValueId,
            'is_calendar_project' => false,
            'deadline_at' => $deadlineAt,
            'event_start_at' => $eventStartAt,
        ]);

        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'deadline_at' => $deadlineAt,
        ]);

        Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'job_definition_id' => $jobDefinition->id,
            'deadline_at' => $deadlineAt,
            'event_start_at' => $eventStartAt,
        ]);

        return $subProject;
    }

    /** Reproduces the live bug: event sub-projects' assignments legitimately have null deadline_at. */
    public function test_event_sub_project_with_event_start_at_can_start_workflow(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $subProject = $this->makeSubProject($actingUser, 'POST_TRANSLATION', null, Date::now()->addDay());

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([SubProjectController::class, 'startWorkflow'], ['id' => $subProject->id]));

        $response->assertOk();
        $this->assertTrue($subProject->refresh()->workflow_started);
    }

    public function test_event_sub_project_without_event_start_at_cannot_start_workflow(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $subProject = $this->makeSubProject($actingUser, 'POST_TRANSLATION', null, null);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([SubProjectController::class, 'startWorkflow'], ['id' => $subProject->id]));

        $response->assertStatus(400);
        $this->assertFalse($subProject->refresh()->workflow_started);
    }

    public function test_deadline_based_sub_project_missing_deadline_cannot_start_workflow(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $subProject = $this->makeSubProject($actingUser, 'TRANSLATION', null, null);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([SubProjectController::class, 'startWorkflow'], ['id' => $subProject->id]));

        $response->assertStatus(400);
        $this->assertFalse($subProject->refresh()->workflow_started);
    }

    public function test_deadline_based_sub_project_with_deadline_can_start_workflow(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $subProject = $this->makeSubProject($actingUser, 'TRANSLATION', Date::now()->addWeek(), null);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([SubProjectController::class, 'startWorkflow'], ['id' => $subProject->id]));

        $response->assertOk();
        $this->assertTrue($subProject->refresh()->workflow_started);
    }
}
