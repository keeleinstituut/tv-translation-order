<?php

namespace App\Http\Requests\API;

use App\Models\Price;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['id', 'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee'],
        properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'character_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'word_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'page_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'minute_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'hour_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'minimal_fee', type: 'number', format: 'double'),
        ]
    )
)]
class PriceUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                Rule::exists(Price::class, 'id'),
            ],
            'character_fee' => 'sometimes|decimal:0,2|between:0,99999999.99',
            'word_fee' => 'sometimes|decimal:0,2|between:0,99999999.99',
            'page_fee' => 'sometimes|decimal:0,2|between:0,99999999.99',
            'minute_fee' => 'sometimes|decimal:0,2|between:0,99999999.99',
            'hour_fee' => 'sometimes|decimal:0,2|between:0,99999999.99',
            'minimal_fee' => 'sometimes|decimal:0,2|between:0,99999999.99',
        ];
    }
}
