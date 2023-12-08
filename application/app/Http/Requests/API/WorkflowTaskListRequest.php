<?php

namespace App\Http\Requests\API;

use App\Enums\TaskType;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Policies\InstitutionUserPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class WorkflowTaskListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'lang_pair' => 'sometimes|array|max:1',
            'lang_pair.*.src' => 'required|uuid',
            'lang_pair.*.dst' => 'required|uuid',
            'type_classifier_value_id' => 'sometimes|array|max:1',
            'type_classifier_value_id.*' => 'required|uuid',
            'sort_by' => Rule::in(['deadline_at']),
            'sort_order' => Rule::in(['asc', 'desc']),
            'assigned_to_me' => 'sometimes|boolean',
            'project_id' => ['sometimes', 'uuid', function ($attribute, $value, $fail) {
                $exists = Project::withGlobalScope('policy', ProjectPolicy::scope())
                    ->where('id', $value)->exists();

                if (! $exists) {
                    $fail('The project with such ID does not exist.');
                }
            }],
            'institution_user_id' => ['sometimes', 'uuid', function ($attribute, $value, $fail) {
                $exists = InstitutionUser::withGlobalScope('policy', InstitutionUserPolicy::scope())
                    ->where('id', $value)->exists();

                if (! $exists) {
                    $fail('The institution user with such ID does not exist.');
                }
            }],
            'task_type' => ['sometimes', new Enum(TaskType::class)]
        ];
    }
}
