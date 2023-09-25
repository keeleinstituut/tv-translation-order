<?php

namespace App\Http\Requests\API;

use App\Enums\Feature;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use App\Rules\SubProjectExistsRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'sub_project_id',
            'feature'
        ],
        properties: [
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'feature', type: 'string', enum: Feature::class),
        ]
    )
)]
class AssignmentCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'sub_project_id' => ['required', 'uuid', new SubProjectExistsRule],
            'feature' => ['string', new Enum(Feature::class)],
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

                $features = $subProject->project->typeClassifierValue->projectTypeConfig->getJobsFeatures();
                $validator->errors()->addIf(
                    $this->validated('feature') !== $features->first(),
                    'feature',
                    'Adding of assignments available only for the feature ' . $features->first()
                );
            }
        ];
    }
}
