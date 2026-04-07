<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Http\Controllers\API\ProjectController;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectTypeConfig;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Support\Facades\Date;
use Tests\AuthHelpers;
use Tests\TestCase;

class ProjectControllerUpdateTest extends TestCase
{
    public function test_update_returns_project_comments_with_institution_user(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $actingUser = InstitutionUser::factory()->createWithPrivileges(
            PrivilegeKey::CreateProject,
            PrivilegeKey::ManageProject,
        );

        // Create project via store endpoint to get a fully initialized project
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->limit(2)->get();
        $projectTypeConfig = ProjectTypeConfig::where('is_start_date_supported', false)->firstOrFail();

        $storeResponse = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                [
                    'type_classifier_value_id' => $projectTypeConfig->type_classifier_value_id,
                    'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                        ->firstOrFail()->id,
                    'deadline_at' => Date::now()->addWeek()->toIso8601ZuluString(),
                    'source_language_classifier_value_id' => $languages[0]->id,
                    'destination_language_classifier_value_ids' => [$languages[1]->id],
                ]
            );

        $storeResponse->assertCreated();
        $projectId = $storeResponse->json('data.id');

        // Add a comment to the project
        $commentAuthor = InstitutionUser::factory()
            ->setInstitution($actingUser->institution)
            ->create();

        $comment = ProjectComment::factory()->create([
            'project_id' => $projectId,
            'institution_user_id' => $commentAuthor->id,
            'comment' => 'Test comment',
        ]);

        // Update the project and verify project_comments include institution_user
        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->putJson(
                action([ProjectController::class, 'update'], ['id' => $projectId]),
                ['comments' => 'Updated project comments field']
            );

        $response->assertOk();

        $response->assertJsonCount(1, 'data.project_comments');
        $response->assertJson([
            'data' => [
                'project_comments' => [
                    [
                        'id' => $comment->id,
                        'comment' => 'Test comment',
                        'institution_user_id' => $commentAuthor->id,
                        'institution_user' => [
                            'id' => $commentAuthor->id,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
