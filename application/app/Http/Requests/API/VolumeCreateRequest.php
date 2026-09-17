<?php

namespace App\Http\Requests\API;

use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Policies\AssignmentPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'assignment_id',
            'unit_type',
            'unit_quantity',
            'unit_fee',
        ],
        properties: [
            new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'unit_type', type: 'string', enum: VolumeUnits::class),
            new OA\Property(property: 'unit_quantity', type: 'number', format: 'double'),
            new OA\Property(property: 'unit_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'discounts', type: 'object'),
            new OA\Property(property: 'custom_volume_analysis', type: 'object'),
        ]
    )
)]
class VolumeCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
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
            'unit_type' => ['required', new Enum(VolumeUnits::class)],
            'unit_quantity' => ['required', 'decimal:0,3', 'min:0.001'],
            'unit_fee' => 'decimal:0,3|between:0,9999.99',
            'discounts.discount_percentage_101' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_repetitions' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_100' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_95_99' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_85_94' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_75_84' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_50_74' => 'sometimes|decimal:0,2|between:0,100',
            'discounts.discount_percentage_0_49' => 'sometimes|decimal:0,2|between:0,100',
            'custom_volume_analysis.tm_101' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.repetitions' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.tm_100' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.tm_95_99' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.tm_85_94' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.tm_75_84' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.tm_50_74' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.tm_0_49' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.raw_word_count' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.total' => 'sometimes|decimal:0,3|min:0',
            'custom_volume_analysis.files_names' => 'sometimes|array',
            'custom_volume_analysis.files_names.*' => 'string',
        ];
    }
}
