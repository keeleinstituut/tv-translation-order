<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use UnitEnum;

trait EntityHelpers
{
    protected function assertModelHasAttributesValues(Model $model, array $attributes): void
    {
        foreach ($attributes as $attribute => $value) {
            $modelAttributeValue = $model->getAttributeValue($attribute);
            if ($modelAttributeValue instanceof UnitEnum) {
                $this->assertEquals($value, $modelAttributeValue->value, $attribute);
            } else {
                $this->assertEquals($value, $model->getAttributeValue($attribute), $attribute);
            }
        }
    }
}
