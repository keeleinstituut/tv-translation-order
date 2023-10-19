<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use App\Rules\SubProjectExistsRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'sub_project_id',
            'source_files_ids',
        ],
        properties: [
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'source_files_ids', type: 'array', items: new OA\Items(type: 'integer')),
        ]
    )
)]
class CatToolSetupRequest extends FormRequest
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
            'source_files_ids' => ['required', 'array'],
            'source_files_ids.*' => [
                'required',
                'integer',
                Rule::exists(Media::class, 'id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $validated = $validator->validated();
                $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())->find($validated['sub_project_id']);
                $countIntersections = $subProject->sourceFiles->pluck('id')
                    ->intersect($validated['source_files_ids'])->count();

                if ($countIntersections !== count($validated['source_files_ids'])) {
                    $validator->errors()->add('source_files_ids', 'Picked source files don\'t belong to specified sub-project');
                }
            },
        ];

    }
}
