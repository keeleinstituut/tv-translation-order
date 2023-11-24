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
        ];
    }
}
