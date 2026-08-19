<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Http\Controllers\API\AssignmentController;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Tests\AuthHelpers;
use Tests\TestCase;

class AssignmentControllerUpdateTest extends TestCase
{
    private function makeAssignment(InstitutionUser $actingUser, string $typeCode, ?Carbon $deadlineAt, ?Carbon $eventStartAt): Assignment
    {
        $typeClassifierValueId = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) use ($typeCode) {
            $query->where('value', $typeCode);
        })->firstOrFail()->type_classifier_value_id;

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

        return Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'deadline_at' => $deadlineAt,
            'event_start_at' => $eventStartAt,
        ]);
    }

    /** Reproduces the live bug: event assignments have null deadline_at, but it was unconditionally required. */
    public function test_event_assignment_update_succeeds_without_deadline_at(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($actingUser, 'POST_TRANSLATION', null, Date::now()->addDay());

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->putJson(
                action([AssignmentController::class, 'update'], ['id' => $assignment->id]),
                ['event_start_at' => Date::now()->addDays(2)->toIso8601ZuluString()]
            );

        $response->assertOk();
        $this->assertNull($assignment->refresh()->deadline_at);
    }

    /** Guards against the null subProject->deadline_at 500 (format() on null). */
    public function test_event_assignment_update_does_not_500_with_null_subproject_deadline(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($actingUser, 'SIGN_LANGUAGE', null, Date::now()->addDay());

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->putJson(
                action([AssignmentController::class, 'update'], ['id' => $assignment->id]),
                ['event_start_at' => Date::now()->addDays(2)->toIso8601ZuluString()]
            );

        $response->assertOk();
    }

    public function test_event_assignment_update_without_event_start_at_is_rejected(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($actingUser, 'POST_TRANSLATION', null, Date::now()->addDay());

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->putJson(
                action([AssignmentController::class, 'update'], ['id' => $assignment->id]),
                ['comments' => 'Just updating comments']
            );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['event_start_at']);
    }

    public function test_deadline_based_assignment_update_still_requires_deadline_at(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($actingUser, 'TRANSLATION', Date::now()->addWeek(), null);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->putJson(
                action([AssignmentController::class, 'update'], ['id' => $assignment->id]),
                ['comments' => 'Just updating comments']
            );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['deadline_at']);
    }

    public function test_deadline_based_assignment_update_with_deadline_at_succeeds(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ManageProject);

        $assignment = $this->makeAssignment($actingUser, 'TRANSLATION', Date::now()->addWeek(), null);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->putJson(
                action([AssignmentController::class, 'update'], ['id' => $assignment->id]),
                ['deadline_at' => Date::now()->addDays(3)->toIso8601ZuluString()]
            );

        $response->assertOk();
    }
}
