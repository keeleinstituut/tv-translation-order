<?php

namespace tests\Feature\Models;

use App\Models\ClassifierValue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClassifierValueTest extends TestCase
{
    public function test_classifier_values_have_readonly_access()
    {
        $id = ClassifierValue::factory()->create()->id;

        $this->expectException(QueryException::class);
        $classifierValue = ClassifierValue::query()->find($id)->first();
        $classifierValue->synced_at = Carbon::now();
        $classifierValue->save();
    }
}
