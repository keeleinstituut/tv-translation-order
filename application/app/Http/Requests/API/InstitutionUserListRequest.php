<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class InstitutionUserListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'limit' => 'sometimes|integer|max:50',
            'fullname' => 'sometimes|string',
            'project_role' => 'sometimes|in:client,manager'
        ];
    }
}
