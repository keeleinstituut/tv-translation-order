<?php

namespace tests\Feature\Integration\Listeners;

use App\Events\ClassifierValues\ClassifierValueSaved;
use App\Listeners\ClassifierValues\SaveClassifierValueListener;
use App\Models\ClassifierValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class SaveClassifierValueListenerTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_classifier_value_updated_event_listened(): void
    {
        $classifierValue = ClassifierValue::factory()->create();
        $newClassifierValueAttributes = $this->generateClassifierValueResponseData($classifierValue->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValueResponse($newClassifierValueAttributes),
        ]);

        $this->app->make(SaveClassifierValueListener::class)
            ->handle(new ClassifierValueSaved($classifierValue->id));

        $classifierValue->refresh();
        $this->assertModelHasAttributesValues($classifierValue, $newClassifierValueAttributes);
    }

    public function test_classifier_value_created_event_listened(): void
    {
        $classifierValueAttributes = $this->generateClassifierValueResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeClassifierValueResponse($classifierValueAttributes),
        ]);

        $this->app->make(SaveClassifierValueListener::class)
            ->handle(new ClassifierValueSaved($classifierValueAttributes['id']));

        $classifierValue = ClassifierValue::withTrashed()
            ->where('id', '=', $classifierValueAttributes['id'])
            ->first();

        $this->assertModelExists($classifierValue);
        $this->assertModelHasAttributesValues($classifierValue, $classifierValueAttributes);
    }

    public function test_classifier_value_created_event_with_404_gateway_response_listened(): void
    {
        $classifierValue = ClassifierValue::factory()->create();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeNotFoundClassifierValueResponse(),
        ]);

        $this->app->make(SaveClassifierValueListener::class)
            ->handle(new ClassifierValueSaved($classifierValue->id));

        $this->assertModelMissing($classifierValue);
    }
}
