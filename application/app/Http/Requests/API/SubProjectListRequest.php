<?php

namespace App\Http\Requests\API;

use App\Enums\SubProjectStatus;
use App\Http\Requests\Helpers\LanguageDirectionValidationTools;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubProjectListRequest extends FormRequest
{
    use LanguageDirectionValidationTools;

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
            'sort_by' => Rule::in(['price', 'deadline_at', 'created_at']),
            'sort_order' => Rule::in(['asc', 'desc']),
            'ext_id' => 'string',
            'only_show_personal_projects' => 'boolean',
            'status' => 'array',
            'status.*' => Rule::enum(SubProjectStatus::class),
            'type_classifier_value_id' => 'array',
            'type_classifier_value_id.*' => [
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'project_id' => [
                'uuid',
                'bail',
                Rule::exists(Project::class, 'id'),
            ],
            'language_direction' => 'array',
            'language_direction.*' => [
                self::getLanguageDirectionValidationRegex(),
                'bail',
                static::validateLanguageDirectionExists(...),
            ],
        ];
    }

    protected function getLanguageDirections(): array
    {
        return $this->validated('language_direction');
    }
}
