<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class VendorListRequest extends FormRequest
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
            'src_lang_classifier_value_id' => 'sometimes|array',
            'src_lang_classifier_value_id.*' => 'uuid',
            'dst_lang_classifier_value_id' => 'sometimes|array',
            'dst_lang_classifier_value_id.*' => 'uuid',
            'role_id' => 'sometimes|uuid',
            'tag_id' => 'sometimes|array',
            'tag_id.*' => 'uuid',
        ];
    }
}
