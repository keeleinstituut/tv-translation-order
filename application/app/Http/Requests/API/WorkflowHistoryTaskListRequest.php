<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
