<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Tag;
use App\Rules\ModelBelongsToInstitutionRule;
use Illuminate\Support\Facades\Auth;

class WorkflowHistoryTaskListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'lang_pair' => 'sometimes|array|max:1',
            'lang_pair.*.src' => 'required|uuid',
            'lang_pair.*.dst' => 'required|uuid',
            'type_classifier_value_id' => 'sometimes|array|max:1',
            'type_classifier_value_id.*' => 'required|uuid',
            'sort_by' => Rule::in([
                'created_at',

                'project.ext_id',
                'project.price',
                'project.deadline_at',
                'project.event_start_at',
                'project.reference_number',
                'project.clientInstitutionUser.name',

                'assignment.subProject.project.ext_id'
            ]),
            'sort_order' => Rule::in(['asc', 'desc']),
            'tag_ids' => 'array',
            'tag_ids.*' => [
                'uuid',
                'bail',
                static::existsTagInSameInstitution(),
            ],
        ];
    }

    private static function existsTagInSameInstitution(): ModelBelongsToInstitutionRule
    {
        return ModelBelongsToInstitutionRule::create(Tag::class, fn () => Auth::user()?->institutionId);
    }
}
