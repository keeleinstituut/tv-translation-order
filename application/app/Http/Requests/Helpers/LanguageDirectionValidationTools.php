<?php

namespace App\Http\Requests\Helpers;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Closure;
use Illuminate\Support\Str;

trait LanguageDirectionValidationTools
{
    const UUID_REGEX = '[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}';

    private static function validateLanguageDirectionExists(string $attribute, mixed $value, Closure $fail): void
    {
        [$sourceLanguage, $destinationLanguage] = static::splitLanguageDirection($value);

        if (ClassifierValue::where(['id' => $sourceLanguage, 'type' => ClassifierValueType::Language->value])->doesntExist()) {
            $fail('The source language of the selected language direction does not exist.');
        }

        if (ClassifierValue::where(['id' => $destinationLanguage, 'type' => ClassifierValueType::Language->value])->doesntExist()) {
            $fail('The destination language of the selected language direction does not exist.');
        }
    }

    private static function splitLanguageDirection(mixed $value): array
    {
        return Str::of($value)->explode(':')->all();
    }

    public function getLanguagesZippedByDirections(): array
    {
        return collect($this->getLanguageDirections())
            ->map(static::splitLanguageDirection(...))
            ->all();
    }

    public static function getLanguageDirectionValidationRegex(): string
    {
        return 'regex:/^'.static::UUID_REGEX.':'.static::UUID_REGEX.'$/';
    }

    /**
     * @return array<string>
     */
    abstract protected function getLanguageDirections(): array;
}
