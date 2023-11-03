<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Enums\TagType;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\ProjectTypeConfig;
use App\Models\Tag;
use Illuminate\Validation\Rule;

class ProjectUpdateRequest extends ProjectCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type_classifier_value_id' => [
                'sometimes',
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'translation_domain_classifier_value_id' => [
                'sometimes',
                'uuid',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::TranslationDomain),
            ],
            'source_language_classifier_value_id' => [
                'sometimes',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
            'destination_language_classifier_value_ids' => ['sometimes', 'array'],
            'destination_language_classifier_value_ids.*' => [
                'required',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
            'manager_institution_user_id' => [
                'sometimes',
                'uuid',
                'bail',
                $this->userCanBeSelectedAsManagerRule(),
            ],
            'client_institution_user_id' => [
                'sometimes',
                'uuid',
                'bail',
                $this->userCanBeSelectedAsClientRule(),
            ],
            'reference_number' => ['nullable', 'string'],
            'comments' => 'sometimes|nullable|string',
            'deadline_at' => ['sometimes', 'date_format:Y-m-d\\TH:i:s\\Z'], // only UTC (zero offset)
            'tags' => 'sometimes|array',
            'tags.*' => [
                'required',
                Rule::exists(Tag::class, 'id')->where('type', TagType::Order->value),
            ],
        ];
    }
}
