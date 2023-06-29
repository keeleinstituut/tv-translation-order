<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Price;

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
