<?php

namespace App\Http\Requests\API;

use App\Enums\OutsourceOfferStatus;
use App\Http\Requests\Helpers\LanguageDirectionValidationTools;
use App\Models\ProjectTypeConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutsourceOfferListRequest extends FormRequest
{
    use LanguageDirectionValidationTools;

    public function rules(): array
    {
        return [
            'q' => 'nullable|string',
            'assignment_id' => 'nullable|uuid',
            'sub_project_id' => 'nullable|uuid',
            'project_id' => 'nullable|uuid',
            'status' => 'nullable|array',
            'status.*' => ['nullable', Rule::enum(OutsourceOfferStatus::class)],
            'per_page' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|string|in:created_at,expires_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'institution_ids' => 'nullable|array',
            'institution_ids.*' => 'uuid',
            'type_classifier_value_ids' => 'array',
            'type_classifier_value_ids.*' => [
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'language_directions' => 'nullable|array',
            'language_directions.*' => [
                self::getLanguageDirectionValidationRegex(),
                'bail',
                static::validateLanguageDirectionExists(...),
            ],
        ];
    }

    protected function getLanguageDirections(): array
    {
        return $this->validated('language_directions') ?? [];
    }
}
