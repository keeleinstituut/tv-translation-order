<?php

namespace App\Http\Requests\API;

use App\Enums\Feature;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use App\Rules\SubProjectExistsRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'sub_project_id',
            'tm_id',
            'is_writable',
            'is_readable',
        ],
        properties: [
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'tm_id', type: 'string'),
            new OA\Property(property: 'is_writable', type: 'boolean'),
            new OA\Property(property: 'is_readable', description: 'In case if empty TM created the field should be false to do not read empty TM', type: 'boolean'),
        ]
    )
)]
class CatToolTmCreateRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'sub_project_id' => [
                'required',
                'uuid',
                new SubProjectExistsRule,
            ],
            'tm_id' => ['required', 'string'],
            'is_writable' => ['required', 'boolean'],
            'is_readable' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                    ->find($this->validated('sub_project_id'));

            },
        ];
    }
}
