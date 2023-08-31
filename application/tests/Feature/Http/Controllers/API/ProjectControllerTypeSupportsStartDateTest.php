<?php

namespace Feature\Http\Controllers\API;

use App\Http\Controllers\API\ProjectController;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\ProjectTypeConfig;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Tests\AuthHelpers;
use Tests\TestCase;

class ProjectControllerTypeSupportsStartDateTest extends TestCase
{
    public function test_classifier_value_supports_start_date(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $type_classifier_value_id = ProjectTypeConfig::where('is_start_date_supported', true)
            ->firstOrFail()
            ->type_classifier_value_id;

        $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser(InstitutionUser::factory()->create()))
            ->getJson(action(
                [ProjectController::class, 'isProjectTypeStartDateCompatible'],
                ['type_classifier_value_id' => $type_classifier_value_id]
            ))
            ->assertOk()
            ->assertJsonPath('data.is_start_date_supported', true);
    }

    public function test_classifier_value_does_not_support_start_date(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $type_classifier_value_id = ProjectTypeConfig::where('is_start_date_supported', false)
            ->firstOrFail()
            ->type_classifier_value_id;

        $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser(InstitutionUser::factory()->create()))
            ->getJson(action(
                [ProjectController::class, 'isProjectTypeStartDateCompatible'],
                ['type_classifier_value_id' => $type_classifier_value_id]
            ))
            ->assertOk()
            ->assertJsonPath('data.is_start_date_supported', false);
    }
}
