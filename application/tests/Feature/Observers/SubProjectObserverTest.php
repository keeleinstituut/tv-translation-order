<?php

namespace Tests\Feature\Observers;

use App\Jobs\CreateCattoProjectJob;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubProjectObserverTest extends TestCase
{
    public function test_creating_sub_project_dispatches_create_catto_project_job_when_cat_tool_enabled(): void
    {
        Queue::fake();

        $projectTypeConfig = ProjectTypeConfig::factory()->create(['cat_tool_enabled' => true]);
        $project = Project::factory()->create([
            'type_classifier_value_id' => $projectTypeConfig->type_classifier_value_id,
        ]);

        $subProject = SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        Queue::assertPushed(
            CreateCattoProjectJob::class,
            fn (CreateCattoProjectJob $job) => $job->subProject->id === $subProject->id,
        );
    }

    public function test_creating_sub_project_does_not_dispatch_job_when_cat_tool_disabled(): void
    {
        Queue::fake();

        $projectTypeConfig = ProjectTypeConfig::factory()->create(['cat_tool_enabled' => false]);
        $project = Project::factory()->create([
            'type_classifier_value_id' => $projectTypeConfig->type_classifier_value_id,
        ]);

        SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        Queue::assertNotPushed(CreateCattoProjectJob::class);
    }
}
