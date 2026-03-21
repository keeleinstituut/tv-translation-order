<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectComment;
use Tests\AuthHelpers;
use Tests\TestCase;

class ProjectCommentControllerTest extends TestCase
{
    public function test_store_creates_comment(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/projects/{$project->id}/comments", [
                'comment' => 'This is a test comment.',
            ]);

        // THEN
        $response
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'project_id', 'institution_user_id', 'comment', 'created_at', 'updated_at']])
            ->assertJson([
                'data' => [
                    'project_id' => $project->id,
                    'institution_user_id' => $institutionUser->id,
                    'comment' => 'This is a test comment.',
                ],
            ]);

        $this->assertDatabaseHas('project_comments', [
            'project_id' => $project->id,
            'institution_user_id' => $institutionUser->id,
            'comment' => 'This is a test comment.',
        ]);
    }

    public function test_store_requires_manage_project_privilege(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ViewInstitutionProjectDetail->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/projects/{$project->id}/comments", [
                'comment' => 'This should not be created.',
            ]);

        // THEN
        $response->assertStatus(403);
    }

    public function test_store_returns_404_for_project_from_other_institution(): void
    {
        // GIVEN — project belongs to a different institution
        $institution = Institution::factory()->create();
        $otherInstitution = Institution::factory()->create();
        $project = Project::factory()->create(['institution_id' => $otherInstitution->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/projects/{$project->id}/comments", [
                'comment' => 'Comment on another institution project.',
            ]);

        // THEN
        $response->assertStatus(404);
    }

    public function test_store_requires_comment_field(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson("/api/projects/{$project->id}/comments", []);

        // THEN
        $response->assertStatus(422)->assertJsonValidationErrors(['comment']);
    }

    public function test_update_modifies_comment_text(): void
    {
        // GIVEN — author with ManageProject privilege
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $project->id,
            'institution_user_id' => $institutionUser->id,
            'comment' => 'Original comment.',
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}/comments/{$comment->id}", [
                'comment' => 'Updated comment.',
            ]);

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => $comment->id, 'comment' => 'Updated comment.']]);

        $this->assertDatabaseHas('project_comments', [
            'id' => $comment->id,
            'comment' => 'Updated comment.',
        ]);
    }

    public function test_update_forbidden_for_non_author(): void
    {
        // GIVEN — comment authored by someone else (a real InstitutionUser for FK constraint)
        $institution = Institution::factory()->create();
        $author = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $otherUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $project->id,
            'institution_user_id' => $author->id,
        ]);

        // Token belongs to a different user
        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $otherUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}/comments/{$comment->id}", [
                'comment' => 'Hijacked.',
            ]);

        // THEN
        $response->assertStatus(403);
    }

    public function test_update_forbidden_without_manage_project_privilege(): void
    {
        // GIVEN — correct author but missing privilege
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $project->id,
            'institution_user_id' => $institutionUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}/comments/{$comment->id}", [
                'comment' => 'No privilege.',
            ]);

        // THEN
        $response->assertStatus(403);
    }

    public function test_update_returns_404_for_comment_belonging_to_different_project(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $otherProject = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $otherProject->id,
            'institution_user_id' => $institutionUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'institutionUserId' => $institutionUser->id,
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN — try to update via the wrong project
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/projects/{$project->id}/comments/{$comment->id}", [
                'comment' => 'Wrong project.',
            ]);

        // THEN
        $response->assertStatus(404);
    }

    public function test_destroy_soft_deletes_comment(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $project->id,
            'institution_user_id' => $institutionUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/projects/{$project->id}/comments/{$comment->id}");

        // THEN
        $response->assertStatus(204);
        $this->assertModelSoftDeleted($comment);
    }

    public function test_destroy_requires_manage_project_privilege(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $project->id,
            'institution_user_id' => $institutionUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/projects/{$project->id}/comments/{$comment->id}");

        // THEN
        $response->assertStatus(403);
        $this->assertNotNull(ProjectComment::find($comment->id));
    }

    public function test_destroy_returns_404_for_comment_from_other_institution(): void
    {
        // GIVEN — project and comment belong to a different institution
        $institution = Institution::factory()->create();
        $otherInstitution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $otherInstitution->id])->create();
        $project = Project::factory()->create(['institution_id' => $otherInstitution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $project->id,
            'institution_user_id' => $institutionUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/projects/{$project->id}/comments/{$comment->id}");

        // THEN
        $response->assertStatus(404);
    }

    public function test_destroy_returns_404_for_comment_belonging_to_different_project(): void
    {
        // GIVEN — comment belongs to a different project in the same institution
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $project = Project::factory()->create(['institution_id' => $institution->id]);
        $otherProject = Project::factory()->create(['institution_id' => $institution->id]);
        $comment = ProjectComment::factory()->create([
            'project_id' => $otherProject->id,
            'institution_user_id' => $institutionUser->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id],
            'privileges' => [PrivilegeKey::ManageProject->value],
        ]);

        // WHEN — try to delete via the wrong project
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/projects/{$project->id}/comments/{$comment->id}");

        // THEN
        $response->assertStatus(404);
    }
}
