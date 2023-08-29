<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Enums\ProjectStatus;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\ProjectTypeConfig;
use App\Models\Tag;
use App\Rules\ModelBelongsToInstitutionRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectListRequest extends FormRequest
{
    const UUID_REGEX = '[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}';

    private static function splitLanguageDirection(mixed $value): array
    {
        return Str::of($value)->explode(':')->all();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'integer',
            'page' => 'integer',
            'sort_by' => Rule::in(['cost', 'deadline_at', 'created_at']),
            'sort_order' => Rule::in(['asc', 'desc']),
            'ext_id' => 'string',
            'only_show_personal_projects' => 'boolean',
            'statuses' => 'array',
            'statuses.*' => Rule::enum(ProjectStatus::class),
            'type_classifier_value_ids' => 'array',
            'type_classifier_value_ids.*' => [
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'tag_ids' => 'array',
            'tag_ids.*' => [
                'uuid',
                'bail',
                static::existsTagInSameInstitution(),
            ],
            'language_directions' => 'array',
            'language_directions.*' => [
                'regex:/^'.static::UUID_REGEX.':'.static::UUID_REGEX.'$/',
                'bail',
                static::validateLanguageDirectionExists(...),
            ],
        ];
    }

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

    private static function existsTagInSameInstitution(): ModelBelongsToInstitutionRule
    {
        return ModelBelongsToInstitutionRule::create(Tag::class, fn () => Auth::user()?->institutionId);
    }

    /** @return array<array{string, string}> */
    public function getLanguagesZippedByDirections(): array
    {
        return collect($this->validated('language_directions'))
            ->map(static::splitLanguageDirection(...))
            ->all();
    }
}
