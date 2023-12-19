<?php

namespace App\Http\Requests\API;

use App\Enums\ProjectStatus;
use App\Http\Requests\Helpers\LanguageDirectionValidationTools;
use App\Models\ProjectTypeConfig;
use App\Models\Tag;
use App\Rules\ModelBelongsToInstitutionRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectListRequest extends FormRequest
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
                self::getLanguageDirectionValidationRegex(),
                'bail',
                static::validateLanguageDirectionExists(...),
            ],
        ];
    }

    private static function existsTagInSameInstitution(): ModelBelongsToInstitutionRule
    {
        return ModelBelongsToInstitutionRule::create(Tag::class, fn () => Auth::user()?->institutionId);
    }

    protected function getLanguageDirections(): array
    {
        return $this->validated('language_directions');
    }
}
