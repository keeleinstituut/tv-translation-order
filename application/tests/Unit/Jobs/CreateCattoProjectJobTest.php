<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CreateCattoProjectJob;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\SubProject;
use App\Services\CattoApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateCattoProjectJobTest extends TestCase
{
    public function test_handle_creates_catto_project_and_persists_returned_id(): void
    {
        $subProject = SubProject::factory()->create([
            'source_language_classifier_value_id' => ClassifierValue::factory()->language()->create(['value' => 'en']),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        $cattoProjectId = fake()->uuid();
        Http::fake([
            rtrim(config('catto.base_url'), '/').'/projects' => Http::response([
                'data' => ['id' => $cattoProjectId, 'name' => $subProject->ext_id, 'source_locale' => 'en'],
            ]),
        ]);

        (new CreateCattoProjectJob($subProject))->handle($this->app->make(CattoApiClient::class));

        Http::assertSent(function ($request) use ($subProject, $cattoProjectId) {
            return $request->url() === rtrim(config('catto.base_url'), '/').'/projects'
                && $request['name'] === $subProject->ext_id
                && $request['source_locale'] === 'en'
                && $request['tenant_id'] === $subProject->project->institution_id
                && $request->hasHeader('Authorization');
        });

        $this->assertSame($cattoProjectId, $subProject->fresh()->cat_metadata['catto_project_id']);
    }

    public function test_handle_is_idempotent_when_catto_project_id_already_persisted(): void
    {
        $subProject = SubProject::factory()->create([
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'cat_metadata' => ['catto_project_id' => 'already-set'],
        ]);

        Http::fake([
            rtrim(config('catto.base_url'), '/').'/projects' => Http::response(['data' => ['id' => 'should-not-be-used']]),
        ]);

        (new CreateCattoProjectJob($subProject))->handle($this->app->make(CattoApiClient::class));

        Http::assertNotSent(fn ($request) => $request->url() === rtrim(config('catto.base_url'), '/').'/projects');
    }
}
