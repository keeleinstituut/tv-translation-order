<?php

namespace App\Http\Requests\API;

use App\Enums\SubProjectStatus;
use App\Http\Requests\Helpers\LanguageDirectionValidationTools;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ModelBelongsToInstitutionRule;
use Illuminate\Support\Facades\Auth;

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
            'q' => 'string',
            'per_page' => 'integer',
            'page' => 'integer',
            'sort_by' => Rule::in([
                'ext_id',
                'project.reference_number',
                'status',
                'price',
                'deadline_at',
                'created_at',
                'project.event_start_at',
                'project.clientInstitutionUser.name',
            ]),
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
            'tag_ids' => 'array',
            'tag_ids.*' => [
                'uuid',
                'bail',
                static::existsTagInSameInstitution(),
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
            'client_institution_user_ids' => 'array',
            'client_institution_user_ids.*' => [
                'uuid',
                'bail',
            ],
            'deadline_at' => 'date_format:Y-m-d',
            'created_at' => 'date_format:Y-m-d',
            'event_start_at' => 'date_format:Y-m-d',
        ];
    }

    private static function existsTagInSameInstitution(): ModelBelongsToInstitutionRule
    {
        return ModelBelongsToInstitutionRule::create(Tag::class, fn () => Auth::user()?->institutionId);
    }

    protected function getLanguageDirections(): array
    {
        return $this->validated('language_direction');
    }
}
