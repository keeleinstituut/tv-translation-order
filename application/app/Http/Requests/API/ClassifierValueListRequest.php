<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class ClassifierValueListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'sometimes|string'
        ];
    }
}
