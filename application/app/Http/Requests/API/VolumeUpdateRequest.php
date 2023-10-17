<?php

namespace App\Http\Requests\API;

use App\Enums\VolumeUnits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'unit_type',
            'unit_quantity',
            'unit_fee',
        ],
        properties: [
            new OA\Property(property: 'unit_type', type: 'string', enum: VolumeUnits::class),
            new OA\Property(property: 'unit_quantity', type: 'number', minimum: 0),
            new OA\Property(property: 'unit_fee', type: 'number', format: 'double', minimum: 0),
        ]
    )
)]
class VolumeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'unit_type' => ['required', new Enum(VolumeUnits::class)],
            'unit_quantity' => ['required', 'integer', 'min:1'],
            'unit_fee' => 'decimal:0,2|between:0,99999999.99',
        ];
    }
}
