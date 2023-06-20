<?php

namespace tests\Feature\Integration\Sync;

use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class ClassifierValueSyncTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_classifier_value_created(): void
    {
        $classifierValueAttributes = $this->generateClassifierValueResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValueResponse($classifierValueAttributes),
        ]);

        $this->artisan('classifier-value:sync', ['id' => $classifierValueAttributes['id']])
            ->assertExitCode(0);

        $classifierValue = ClassifierValue::withTrashed()
            ->where('id', '=', $classifierValueAttributes['id'])
            ->first();

        $this->assertModelExists($classifierValue);
        $this->assertModelHasAttributesValues($classifierValue, $classifierValueAttributes);
    }

    public function test_classifier_value_updated(): void
    {
        $classifierValue = ClassifierValue::factory()->create();
        $newClassifierValueAttributes = $this->generateClassifierValueResponseData($classifierValue->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValueResponse($newClassifierValueAttributes),
        ]);

        $this->artisan('classifier-value:sync', ['id' => $classifierValue->id])
            ->assertExitCode(0);

        $classifierValue->refresh();
        $this->assertModelExists($classifierValue);
        $this->assertModelHasAttributesValues($classifierValue, $newClassifierValueAttributes);
    }

    public function test_classifier_value_deleted(): void
    {
        $classifierValue = ClassifierValue::factory()->create();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeNotFoundClassifierValueResponse(),
        ]);

        $this->artisan('classifier-value:sync', ['id' => $classifierValue->id])->assertExitCode(0);
        $this->assertModelMissing($classifierValue);
    }
}
