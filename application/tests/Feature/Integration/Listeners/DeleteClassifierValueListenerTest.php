<?php

namespace tests\Feature\Integration\Listeners;

use App\Events\ClassifierValues\ClassifierValueDeleted;
use App\Listeners\ClassifierValues\DeleteClassifierValueListener;
use App\Models\CachedEntities\ClassifierValue;
use Tests\TestCase;

class DeleteClassifierValueListenerTest extends TestCase
{
    public function test_classifier_value_deleted_event_listened(): void
    {
        $classifierValue = ClassifierValue::factory()->create();
        $this->app->make(DeleteClassifierValueListener::class)
            ->handle(new ClassifierValueDeleted($classifierValue->id));
        $this->assertModelSoftDeleted($classifierValue);
    }
}
