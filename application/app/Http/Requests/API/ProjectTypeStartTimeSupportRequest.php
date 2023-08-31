<?php

namespace App\Http\Requests\API;

use App\Models\ProjectTypeConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectTypeStartTimeSupportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type_classifier_value_id' => [
                'required',
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
        ];
    }
}
