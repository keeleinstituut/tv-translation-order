<?php

namespace App\Http\Requests\API;

use App\Models\SubProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatToolSplitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'sub_project_id' => [
                'required',
                'uuid',
                Rule::exists(SubProject::class, 'id'),
            ],
            'chunks_count' => [
                'required',
                'integer',
                'between:2,50'
            ]
        ];
    }
}
