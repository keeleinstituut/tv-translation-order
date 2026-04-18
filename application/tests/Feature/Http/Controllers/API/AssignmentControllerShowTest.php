<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use Tests\AuthHelpers;
use Tests\TestCase;

class AssignmentControllerShowTest extends TestCase
{
    private Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institution = Institution::factory()->create();
    }

    public function test_assigned_vendor_can_view_assignment(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'sub_project_id',
                    'ext_id',
                    'status',
                    'deadline_at',
                    'comments',
                    'assignee_comments',
                    'created_at',
                    'updated_at',
                    'job_definition',
                    'assignee',
                    'candidates',
                    'volumes',
                    'cat_jobs',
                    'subProject',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $assignment->id,
                ],
            ]);
    }

    public function test_candidate_vendor_can_view_assignment(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        Candidate::factory()->create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $assignment->id,
                ],
            ]);
    }

    public function test_unrelated_vendor_gets_forbidden(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => null,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertForbidden();
    }

    public function test_vendor_from_different_institution_gets_not_found(): void
    {
        // GIVEN
        $otherInstitution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $otherInstitution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $otherInstitution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response->assertNotFound();
    }

    public function test_response_includes_sub_project_with_languages_and_project(): void
    {
        // GIVEN
        $institutionUser = InstitutionUser::factory()
            ->setInstitution(['id' => $this->institution->id])
            ->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $project = Project::factory()->create(['institution_id' => $this->institution->id]);
        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $subProject->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/assignments/{$assignment->id}");

        // THEN
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'subProject' => [
                        'id',
                        'project_id',
                        'source_language_classifier_value',
                        'destination_language_classifier_value',
                        'project' => [
                            'id',
                            'type_classifier_value',
                            'translation_domain_classifier_value',
                            'tags',
                        ],
                    ],
                ],
            ]);
    }
}
