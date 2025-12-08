<?php

namespace tests\Feature\Integration\Sync;

use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class ClassifierValueFullSyncTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_classifier_values_synced(): void
    {
        $classifierValueAttributes = $this->generateClassifierValueResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValuesResponse([
                $classifierValueAttributes,
            ]),
        ]);

        $this->artisan('sync:classifier-values')->assertExitCode(0);

        $classifierValue = ClassifierValue::withTrashed()
            ->where('id', '=', $classifierValueAttributes['id'])
            ->first();

        $this->assertNotNull($classifierValue);
        $this->assertModelExists($classifierValue);
        $this->assertModelHasAttributesValues($classifierValue, $classifierValueAttributes);
    }

    public function test_not_synced_classifier_values_removed(): void
    {
        $classifierValues = ClassifierValue::factory(10)->create(['synced_at' => Carbon::now()->subDay()]);

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValuesResponse(),
        ]);

        $this->artisan('sync:classifier-values')->assertExitCode(0);
        foreach ($classifierValues as $classifierValue) {
            // Refresh the model from database to get current state
            $classifierValue->refresh();
            $this->assertModelSoftDeleted($classifierValue);
        }
    }

    public function test_synced_classifier_values_updated(): void
    {
        $classifierValue = ClassifierValue::factory()->create();
        $newClassifierValueAttributes = $this->generateClassifierValueResponseData($classifierValue->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValuesResponse([
                $newClassifierValueAttributes,
            ]),
        ]);

        $this->artisan('sync:classifier-values')->assertExitCode(0);

        $classifierValue->refresh();
        $this->assertModelExists($classifierValue);
        $this->assertModelHasAttributesValues($classifierValue, $newClassifierValueAttributes);
    }
}
