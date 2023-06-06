<?php

namespace App\Rules;

use BackedEnum;
use Illuminate\Validation\Rules\Enum;
use UnexpectedValueException;

class EnumWithExcludedItems extends Enum
{
    protected array $excludedEnumItems;

    public function __construct(string $type, array $excludedEnumItems)
    {
        parent::__construct($type);
        foreach ($excludedEnumItems as $excludedItem) {
            if (! $excludedItem instanceof $this->type) {
                throw new UnexpectedValueException();
            }
        }
        $this->excludedEnumItems = $excludedEnumItems;
    }

    public function passes($attribute, $value): bool
    {
        if (! parent::passes($attribute, $value)) {
            return false;
        }

        return ! in_array($this->getEnumInstance($value), $this->excludedEnumItems);
    }

    protected function getEnumInstance(string $value): BackedEnum
    {
        return $this->type::tryFrom($value);
    }
}
