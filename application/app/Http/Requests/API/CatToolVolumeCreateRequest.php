<?php

namespace App\Http\Requests\API;

use App\Http\Resources\API\VolumeAnalysisDiscountResource;
use App\Http\Resources\API\VolumeAnalysisResource;
use App\Models\Assignment;
use App\Models\AssignmentCatToolJob;
use App\Models\CatToolJob;
use App\Policies\AssignmentPolicy;
use App\Policies\CatToolJobPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'assignment_id',
            'cat_tool_job_id',
            'unit_fee',
            'custom_volume_analysis',
            'discounts',
        ],
        properties: [
            new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'cat_tool_job_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'unit_fee', type: 'number', format: 'double', minimum: 0),
            new OA\Property(property: 'custom_volume_analysis', ref: VolumeAnalysisResource::class),
            new OA\Property(property: 'discounts', ref: VolumeAnalysisDiscountResource::class),
        ]
    )
)]
class CatToolVolumeCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $percentageRule = 'sometimes|decimal:0,2|between:0,100.00';
        $unitQualityRule = ['sometimes', 'decimal:0,3', 'min:0'];

        return [
            'assignment_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('The assignment with such ID does not exist.');
                    }
                },
            ],
            'unit_fee' => 'decimal:0,3|between:0,9999.99',
            'cat_tool_job_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = CatToolJob::withGlobalScope('policy', CatToolJobPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('The XLIFF with such ID does not exist.');
                    }
                },
            ],
            'custom_volume_analysis.tm_101' => $unitQualityRule,
            'custom_volume_analysis.repetitions' => $unitQualityRule,
            'custom_volume_analysis.tm_100' => $unitQualityRule,
            'custom_volume_analysis.tm_95_99' => $unitQualityRule,
            'custom_volume_analysis.tm_85_94' => $unitQualityRule,
            'custom_volume_analysis.tm_75_84' => $unitQualityRule,
            'custom_volume_analysis.tm_50_74' => $unitQualityRule,
            'custom_volume_analysis.tm_0_49' => $unitQualityRule,
            'discounts.discount_percentage_101' => $percentageRule,
            'discounts.discount_percentage_repetitions' => $percentageRule,
            'discounts.discount_percentage_100' => $percentageRule,
            'discounts.discount_percentage_95_99' => $percentageRule,
            'discounts.discount_percentage_85_94' => $percentageRule,
            'discounts.discount_percentage_75_84' => $percentageRule,
            'discounts.discount_percentage_50_74' => $percentageRule,
            'discounts.discount_percentage_0_49' => $percentageRule,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $assignment = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
                    ->find($this->validated('assignment_id'));
                $catToolJob =  CatToolJob::withGlobalScope('policy', CatToolJobPolicy::scope())
                    ->find($this->validated('cat_tool_job_id'));

                $validator->errors()->addIf(
                    $assignment?->sub_project_id !== $catToolJob?->sub_project_id,
                    'cat_tool_job_id',
                    'XLIFF file belongs to another sub-project.'
                );
            },
        ];
    }
}
