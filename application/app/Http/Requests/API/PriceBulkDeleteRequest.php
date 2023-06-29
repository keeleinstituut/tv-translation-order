<?php

namespace App\Http\Requests\API;

use App\Models\Price;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceBulkDeleteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|array|min:1',
            'id.*' => [
                'uuid',
                'distinct',
                Rule::exists(Price::class, 'id'),
            ],
        ];
    }
}
